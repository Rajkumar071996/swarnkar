<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreExpense;
use App\Models\StoreIncome;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The shop's till and bank. Capital is what the owner put in at the start;
 * cash and bank move as girvi goes out, money comes in, and expenses are paid.
 */
class StoreBooks
{
    /**
     * @return array{capital: float, cash: float, bank: float, income: float, expenses: float}
     */
    public function snapshot(Store $store): array
    {
        return [
            'capital' => round((float) $store->opening_capital, 2),
            'cash' => round((float) $store->cash_in_hand, 2),
            'bank' => round((float) $store->bank_balance, 2),
            'income' => round((float) StoreIncome::query()
                ->where('store_id', $store->id)
                ->sum('amount'), 2),
            'expenses' => round((float) StoreExpense::query()
                ->where('store_id', $store->id)
                ->sum('amount'), 2),
        ];
    }

    public function debit(Store $store, string $wallet, float $amount, string $label = 'amount'): void
    {
        if ($amount <= 0.009) {
            return;
        }

        DB::transaction(function () use ($store, $wallet, $amount, $label) {
            $locked = $this->lock($store);
            $column = $this->column($wallet);
            $available = round((float) $locked->{$column}, 2);

            if ($amount > $available + 0.009) {
                throw ValidationException::withMessages([
                    $label => 'Only '.money($available).' is available in '.$this->label($wallet).'.',
                ]);
            }

            $locked->{$column} = round($available - $amount, 2);
            $locked->save();
        });

        $store->refresh();
    }

    public function credit(Store $store, string $wallet, float $amount): void
    {
        if ($amount <= 0.009) {
            return;
        }

        DB::transaction(function () use ($store, $wallet, $amount) {
            $locked = $this->lock($store);
            $column = $this->column($wallet);
            $locked->{$column} = round((float) $locked->{$column} + $amount, 2);
            $locked->save();
        });

        $store->refresh();
    }

    public function recordExpense(
        Store $store,
        float $amount,
        string $wallet,
        Carbon $paidOn,
        string $narration,
        User $user,
    ): StoreExpense {
        if ($amount <= 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an expense greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($store, $amount, $wallet, $paidOn, $narration, $user) {
            $this->debit($store, $wallet, $amount);

            return StoreExpense::create([
                'store_id' => $store->id,
                'amount' => $amount,
                'paid_from' => $wallet,
                'paid_on' => $paidOn,
                'narration' => $narration,
                'recorded_by_user_id' => $user->id,
            ]);
        });
    }

    /**
     * Money that came in after opening — an investment, or an amount someone
     * handed over. An investment also raises capital; ordinary income does not.
     */
    public function recordIncome(
        Store $store,
        float $amount,
        string $wallet,
        string $kind,
        Carbon $receivedOn,
        string $narration,
        User $user,
    ): StoreIncome {
        if ($amount <= 0.009) {
            throw ValidationException::withMessages([
                'income_amount' => 'Enter an amount greater than zero.',
            ]);
        }

        if (! in_array($kind, ['income', 'investment'], true)) {
            throw ValidationException::withMessages([
                'kind' => 'Say whether this is income or an investment.',
            ]);
        }

        return DB::transaction(function () use ($store, $amount, $wallet, $kind, $receivedOn, $narration, $user) {
            $this->credit($store, $wallet, $amount);

            if ($kind === 'investment') {
                $locked = $this->lock($store);
                $locked->opening_capital = round((float) $locked->opening_capital + $amount, 2);
                $locked->save();
                $store->refresh();
            }

            return StoreIncome::create([
                'store_id' => $store->id,
                'amount' => $amount,
                'kind' => $kind,
                'received_in' => $wallet,
                'received_on' => $receivedOn,
                'narration' => $narration,
                'recorded_by_user_id' => $user->id,
            ]);
        });
    }

    /**
     * Cash stays in the till. UPI, card, cheque and a bank transfer land in the bank.
     */
    public function walletForMethod(string $method): string
    {
        return $method === 'cash' ? 'cash' : 'bank';
    }

    private function lock(Store $store): Store
    {
        return Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
    }

    private function column(string $wallet): string
    {
        return match ($wallet) {
            'bank' => 'bank_balance',
            default => 'cash_in_hand',
        };
    }

    private function label(string $wallet): string
    {
        return $wallet === 'bank' ? 'the bank' : 'cash in hand';
    }
}
