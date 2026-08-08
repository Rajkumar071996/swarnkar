<?php

namespace App\Services;

use App\Enums\UdhaarStatus;
use App\Events\CustomerLedgerChanged;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Udhaar;
use App\Models\UdhaarPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UdhaarLedger
{
    public function __construct(private readonly KhataAdvanceService $advances) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function issue(array $attributes, User $user): Udhaar
    {
        return DB::transaction(function () use ($attributes, $user) {
            $udhaar = Udhaar::create([
                ...$attributes,
                'store_id' => $user->store_id,
                'created_by_user_id' => $user->id,
            ]);

            $udhaar->syncStatus();
            $udhaar->save();

            $this->applyAdvanceToUdhaar($udhaar, $user);

            AuditLog::record('udhaar.issued', $udhaar, [
                'customer_id' => $udhaar->customer_id,
                'principal' => (float) $udhaar->principal_amount,
            ]);

            CustomerLedgerChanged::dispatch($udhaar->customer, 'udhaar.issued');

            return $udhaar->fresh();
        });
    }

    public function recordPayment(Udhaar $udhaar, float $amount, Carbon $paidOn, string $method, ?string $reference, User $user): UdhaarPayment
    {
        if ($amount > $udhaar->outstandingAmount() + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'That is more than the '.money($udhaar->outstandingAmount()).' still outstanding.',
            ]);
        }

        return DB::transaction(function () use ($udhaar, $amount, $paidOn, $method, $reference, $user) {
            $payment = $udhaar->payments()->create([
                'amount' => $amount,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'recorded_by_user_id' => $user->id,
            ]);

            $udhaar->load('payments');
            $udhaar->syncStatus();
            $udhaar->save();

            AuditLog::record('udhaar.payment_recorded', $udhaar, ['amount' => $amount]);

            CustomerLedgerChanged::dispatch($udhaar->customer, 'udhaar.payment');

            return $payment;
        });
    }

    /**
     * Records money received against a customer's khata. Applied to open bills
     * first (FIFO or a chosen bill); any surplus is kept as an advance.
     *
     * @return array{payments: Collection<int, UdhaarPayment>, advance_credited: float}
     */
    public function receive(
        Customer $customer,
        float $amount,
        Carbon $paidOn,
        string $method,
        ?string $reference,
        User $user,
        ?int $udhaarId = null,
    ): array {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter how much was received.',
            ]);
        }

        $open = Udhaar::query()
            ->where('customer_id', $customer->id)
            ->where('store_id', $user->store_id)
            ->outstanding()
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        if ($udhaarId !== null) {
            $open = $open->where('id', $udhaarId)->values();

            if ($open->isEmpty()) {
                throw ValidationException::withMessages([
                    'udhaar_id' => 'That credit entry is not open on this khata.',
                ]);
            }
        }

        return DB::transaction(function () use ($open, $amount, $paidOn, $method, $reference, $user, $customer) {
            $remaining = $amount;
            $payments = collect();

            foreach ($open as $udhaar) {
                if ($remaining <= 0.009) {
                    break;
                }

                $slice = min($remaining, $udhaar->outstandingAmount());
                $payments->push($this->recordPayment(
                    $udhaar->fresh(),
                    $slice,
                    $paidOn,
                    $method,
                    $reference,
                    $user,
                ));
                $remaining = round($remaining - $slice, 2);
            }

            $advanceCredited = 0.0;

            if ($remaining > 0.009) {
                $this->advances->credit(
                    $customer,
                    $user->store_id,
                    $remaining,
                    $paidOn,
                    $method,
                    $reference,
                    $user,
                    'Received entry advance',
                );
                $advanceCredited = $remaining;
            }

            AuditLog::record('khata.receipt_recorded', $customer, [
                'amount' => $amount,
                'payments' => $payments->count(),
                'advance_credited' => $advanceCredited,
            ]);

            return [
                'payments' => $payments,
                'advance_credited' => $advanceCredited,
            ];
        });
    }

    public function writeOff(Udhaar $udhaar, User $user, ?string $notes = null): void
    {
        DB::transaction(function () use ($udhaar, $user, $notes) {
            // Status is deliberately outside the fillable list so it can only
            // move through the ledger's own methods.
            $udhaar->status = UdhaarStatus::WrittenOff;
            $udhaar->notes = trim(($udhaar->notes ? $udhaar->notes."\n" : '')
                .'Written off by '.$user->name.($notes ? ': '.$notes : ''));
            $udhaar->save();

            AuditLog::record('udhaar.written_off', $udhaar, [
                'outstanding' => $udhaar->outstandingAmount(),
            ]);

            CustomerLedgerChanged::dispatch($udhaar->customer, 'udhaar.written_off');
        });
    }

    /**
     * Rolls open accounts that have passed the default threshold into the
     * defaulted state so the ledger reflects the calendar without anyone
     * having to touch each row.
     */
    public function ageOpenAccounts(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::today();
        $cutoff = $asOf->copy()->subDays((int) config('goldscore.udhaar.default_days'));

        return Udhaar::query()
            ->whereIn('status', [UdhaarStatus::Open->value, UdhaarStatus::PartiallyPaid->value])
            ->whereDate('due_on', '<', $cutoff)
            ->update(['status' => UdhaarStatus::Defaulted->value]);
    }

    private function applyAdvanceToUdhaar(Udhaar $udhaar, User $user): void
    {
        $available = $this->advances->balance($udhaar->customer, $udhaar->store_id);
        $slice = min($available, $udhaar->outstandingAmount());

        if ($slice <= 0.009) {
            return;
        }

        $this->advances->debit(
            $udhaar->customer,
            $udhaar->store_id,
            $slice,
            $udhaar->issued_on ?? Carbon::today(),
            $user,
            $udhaar->id,
        );

        $this->recordPayment(
            $udhaar->fresh(),
            $slice,
            $udhaar->issued_on ?? Carbon::today(),
            'advance',
            null,
            $user,
        );
    }
}
