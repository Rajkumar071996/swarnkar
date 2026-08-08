<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\KhataAdvance;
use App\Models\KhataAdvanceEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Own-store advance balances: money received before (or beyond) open credit,
 * later consumed when a new udhaar is issued.
 */
class KhataAdvanceService
{
    public function balance(Customer $customer, int $storeId): float
    {
        return round((float) (KhataAdvance::query()
            ->where('store_id', $storeId)
            ->where('customer_id', $customer->id)
            ->value('balance') ?? 0), 2);
    }

    public function credit(
        Customer $customer,
        int $storeId,
        float $amount,
        Carbon $paidOn,
        string $method,
        ?string $reference,
        User $user,
        ?string $notes = null,
    ): KhataAdvanceEntry {
        if ($amount <= 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Advance credit must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($customer, $storeId, $amount, $paidOn, $method, $reference, $user, $notes) {
            $advance = $this->lockOrCreate($storeId, $customer->id);
            $advance->balance = round((float) $advance->balance + $amount, 2);
            $advance->save();

            return KhataAdvanceEntry::create([
                'store_id' => $storeId,
                'customer_id' => $customer->id,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'recorded_by_user_id' => $user->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Reduce the advance balance and write a negative ledger entry. Caller is
     * responsible for recording the matching udhaar payment.
     */
    public function debit(
        Customer $customer,
        int $storeId,
        float $amount,
        Carbon $paidOn,
        User $user,
        ?int $udhaarId = null,
        ?string $notes = null,
    ): KhataAdvanceEntry {
        if ($amount <= 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Advance debit must be greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($customer, $storeId, $amount, $paidOn, $user, $udhaarId, $notes) {
            $advance = $this->lockOrCreate($storeId, $customer->id);
            $available = round((float) $advance->balance, 2);

            if ($amount > $available + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'Only '.money($available).' advance is available.',
                ]);
            }

            $advance->balance = round($available - $amount, 2);
            $advance->save();

            return KhataAdvanceEntry::create([
                'store_id' => $storeId,
                'customer_id' => $customer->id,
                'amount' => -$amount,
                'paid_on' => $paidOn,
                'method' => 'advance',
                'udhaar_id' => $udhaarId,
                'recorded_by_user_id' => $user->id,
                'notes' => $notes ?? 'Applied to credit entry',
            ]);
        });
    }

    private function lockOrCreate(int $storeId, int $customerId): KhataAdvance
    {
        $advance = KhataAdvance::query()
            ->where('store_id', $storeId)
            ->where('customer_id', $customerId)
            ->lockForUpdate()
            ->first();

        if ($advance) {
            return $advance;
        }

        return KhataAdvance::create([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'balance' => 0,
        ]);
    }
}
