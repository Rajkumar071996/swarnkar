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

            AuditLog::record('udhaar.issued', $udhaar, [
                'customer_id' => $udhaar->customer_id,
                'principal' => (float) $udhaar->principal_amount,
            ]);

            CustomerLedgerChanged::dispatch($udhaar->customer, 'udhaar.issued');

            return $udhaar;
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
     * Records money received against a customer's khata. The jeweller can aim
     * it at one bill, or leave the target blank and the receipt is applied to
     * the oldest outstanding entries first — the way a running khata is kept.
     *
     * @return Collection<int, UdhaarPayment>
     */
    public function receive(
        Customer $customer,
        float $amount,
        Carbon $paidOn,
        string $method,
        ?string $reference,
        User $user,
        ?int $udhaarId = null,
    ): Collection {
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

        $available = round($open->sum(fn (Udhaar $u) => $u->outstandingAmount()), 2);

        if ($available <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'This customer has nothing outstanding at your store.',
            ]);
        }

        if ($amount > $available + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'That is more than the '.money($available).' still outstanding on this khata.',
            ]);
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

            AuditLog::record('khata.receipt_recorded', $customer, [
                'amount' => $amount,
                'payments' => $payments->count(),
            ]);

            return $payments;
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
}
