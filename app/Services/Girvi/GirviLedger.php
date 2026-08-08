<?php

namespace App\Services\Girvi;

use App\Enums\GoldLoanStatus;
use App\Events\CustomerLedgerChanged;
use App\Models\AuditLog;
use App\Models\GoldLoan;
use App\Models\GoldLoanPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The girvi book: jewellery in against cash out, interest collected while it
 * sits with the shop, and the pledge handed back when the account is cleared.
 *
 * Every write raises CustomerLedgerChanged so a pledge moves the customer's
 * GoldScore the same way store credit does.
 */
class GirviLedger
{
    public function __construct(
        private readonly GoldValuation $valuation,
        private readonly InterestCalculator $interest,
        private readonly ReceiptNumber $receipts,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $itemRows
     */
    public function deposit(array $attributes, array $itemRows, User $user): GoldLoan
    {
        if ($itemRows === []) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item to the pledge.',
            ]);
        }

        $priced = $this->valuation->priceItems($itemRows);
        $estimatePercent = (float) ($attributes['estimate_percent'] ?? config('girvi.estimate_percent'));
        $estimateAmount = $this->valuation->estimateAmount($priced['total_value'], $estimatePercent);
        $principal = round((float) $attributes['principal_amount'], 2);

        if ($principal > $estimateAmount + 0.009) {
            throw ValidationException::withMessages([
                'principal_amount' => 'The loan cannot exceed the estimate of '.money($estimateAmount).'.',
            ]);
        }

        $disbursedOn = Carbon::parse($attributes['disbursed_on']);
        $durationMonths = (int) ($attributes['duration_months'] ?? config('girvi.duration_months'));

        return DB::transaction(function () use (
            $attributes, $priced, $estimatePercent, $estimateAmount,
            $principal, $disbursedOn, $durationMonths, $user
        ) {
            $loan = GoldLoan::create([
                'store_id' => $user->store_id,
                'customer_id' => $attributes['customer_id'],
                'loan_no' => $this->receipts->forLoan($user->store_id),
                'receipt_no' => $this->receipts->forDeposit($user->store_id),
                'invoice_no' => $attributes['invoice_no'] ?? null,
                'packet_no' => $attributes['packet_no'] ?? null,
                'barcode' => $attributes['barcode'] ?? null,
                'principal_amount' => $principal,
                'interest_rate' => $attributes['interest_rate'] ?? config('girvi.interest_rate'),
                'duration_months' => $durationMonths,
                'gross_weight_grams' => $priced['gross_weight_grams'],
                'less_weight_grams' => $priced['less_weight_grams'],
                'net_weight_grams' => $priced['net_weight_grams'],
                'fine_weight_grams' => $priced['fine_weight_grams'],
                'rate_per_gram' => $attributes['rate_per_gram'] ?? 0,
                'total_value' => $priced['total_value'],
                'estimate_percent' => $estimatePercent,
                'estimate_amount' => $estimateAmount,
                // Kept in step with the scoring columns that predate the module.
                'pledged_weight_grams' => $priced['net_weight_grams'],
                'purity_karat' => $this->karatFromItems($priced['items']),
                'loan_reason' => $attributes['loan_reason'] ?? null,
                'loan_type' => $attributes['loan_type'] ?? null,
                'refer_by' => $attributes['refer_by'] ?? null,
                'narration' => $attributes['narration'] ?? null,
                'disbursed_on' => $disbursedOn,
                'due_on' => $disbursedOn->copy()->addMonths($durationMonths),
                'status' => GoldLoanStatus::Active,
                'created_by_user_id' => $user->id,
            ]);

            $loan->items()->createMany($priced['items']);

            AuditLog::record('girvi.deposited', $loan, [
                'customer_id' => $loan->customer_id,
                'principal' => $principal,
                'fine_weight' => $priced['fine_weight_grams'],
            ]);

            CustomerLedgerChanged::dispatch($loan->customer, 'girvi.deposited');

            return $loan->fresh(['items']);
        });
    }

    public function collectInterest(
        GoldLoan $loan,
        float $amount,
        Carbon $paidOn,
        string $method,
        ?string $reference,
        User $user,
    ): GoldLoanPayment {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter how much interest was collected.',
            ]);
        }

        if ($loan->isReleased()) {
            throw ValidationException::withMessages([
                'amount' => 'This pledge has already been released.',
            ]);
        }

        return DB::transaction(function () use ($loan, $amount, $paidOn, $method, $reference, $user) {
            $payment = $loan->payments()->create([
                'amount' => $amount,
                'type' => 'interest',
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'recorded_by_user_id' => $user->id,
            ]);

            $loan->interest_collected = round((float) $loan->interest_collected + $amount, 2);
            $loan->save();

            AuditLog::record('girvi.interest_collected', $loan, ['amount' => $amount]);

            CustomerLedgerChanged::dispatch($loan->customer, 'girvi.interest');

            return $payment;
        });
    }

    /**
     * Clears the account and hands the jewellery back.
     *
     * @param  array<string, float>  $charges
     * @return array<string, mixed>
     */
    public function release(GoldLoan $loan, Carbon $releaseOn, array $charges, User $user): array
    {
        if ($loan->isReleased()) {
            throw ValidationException::withMessages([
                'release' => 'This pledge has already been released.',
            ]);
        }

        $summary = $this->interest->releaseSummary($loan, $releaseOn, $charges);

        return DB::transaction(function () use ($loan, $releaseOn, $summary, $user) {
            $receiptNo = $this->receipts->forRelease($loan->store_id);

            $loan->payments()->create([
                'amount' => $summary['total'],
                'type' => 'principal',
                'receipt_no' => $receiptNo,
                'discount' => $summary['discount'],
                'paid_on' => $releaseOn,
                'method' => 'cash',
                'notes' => 'Release settlement',
                'recorded_by_user_id' => $user->id,
            ]);

            $loan->forceFill([
                'principal_repaid' => $loan->principal_amount,
                'interest_collected' => round($summary['interest_paid'] + $summary['interest_payable'], 2),
                'extra_amount' => $summary['extra_amount'],
                'notice_charge' => $summary['notice_charge'],
                'discount' => $summary['discount'],
                'released_on' => $releaseOn,
                'closed_on' => $releaseOn,
                'status' => GoldLoanStatus::Closed,
            ])->save();

            AuditLog::record('girvi.released', $loan, [
                'total' => $summary['total'],
                'interest' => $summary['interest_due'],
                'receipt_no' => $receiptNo,
            ]);

            CustomerLedgerChanged::dispatch($loan->customer, 'girvi.released');

            return [...$summary, 'receipt_no' => $receiptNo];
        });
    }

    /**
     * The scoring tables predate the module and still speak in karat, so the
     * heaviest item's purity is carried across.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function karatFromItems(array $items): int
    {
        $heaviest = collect($items)->sortByDesc('fine_weight_grams')->first();
        $percent = (float) ($heaviest['weight_percent'] ?? 100);

        return (int) round($percent / 100 * 24);
    }
}
