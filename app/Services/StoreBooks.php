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
 * Each product has its own till. GoldScore books are the shop's udhaar
 * capital; girvi books are the pledge counter. Mixing them made both
 * dashboards show the same numbers.
 */
class StoreBooks
{
    public const GOLDSCORE = 'goldscore';

    public const GIRVI = 'girvi';

    public function resolveModule(?string $module): string
    {
        return $module === self::GIRVI ? self::GIRVI : self::GOLDSCORE;
    }

    /**
     * @return array{capital: float, cash: float, bank: float, income: float, expenses: float, profit: float, module: string}
     */
    public function snapshot(Store $store, ?string $module = null): array
    {
        $module = $this->resolveModule($module);
        $columns = $this->columns($module);

        $income = round((float) StoreIncome::query()
            ->where('store_id', $store->id)
            ->where('module', $module)
            ->where('kind', 'income')
            ->sum('amount'), 2);
        $expenses = round((float) StoreExpense::query()
            ->where('store_id', $store->id)
            ->where('module', $module)
            ->sum('amount'), 2);

        return [
            'module' => $module,
            'capital' => round((float) $store->{$columns['capital']}, 2),
            'cash' => round((float) $store->{$columns['cash']}, 2),
            'bank' => round((float) $store->{$columns['bank']}, 2),
            'income' => $income,
            'expenses' => $expenses,
            'profit' => round($income - $expenses, 2),
        ];
    }

    public function debit(Store $store, string $wallet, float $amount, string $label = 'amount', ?string $module = null): void
    {
        if ($amount <= 0.009) {
            return;
        }

        $module = $this->resolveModule($module);

        DB::transaction(function () use ($store, $wallet, $amount, $label, $module) {
            $locked = $this->lock($store);
            $column = $this->column($wallet, $module);
            $available = round((float) $locked->{$column}, 2);

            if ($amount > $available + 0.009) {
                throw ValidationException::withMessages([
                    $label => 'Only '.money($available).' is available in '.$this->label($wallet, $module).'.',
                ]);
            }

            $locked->{$column} = round($available - $amount, 2);
            $locked->save();
        });

        $store->refresh();
    }

    public function credit(Store $store, string $wallet, float $amount, ?string $module = null): void
    {
        if ($amount <= 0.009) {
            return;
        }

        $module = $this->resolveModule($module);

        DB::transaction(function () use ($store, $wallet, $amount, $module) {
            $locked = $this->lock($store);
            $column = $this->column($wallet, $module);
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
        ?string $module = null,
    ): StoreExpense {
        if ($amount <= 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an expense greater than zero.',
            ]);
        }

        $module = $this->resolveModule($module);

        return DB::transaction(function () use ($store, $amount, $wallet, $paidOn, $narration, $user, $module) {
            $this->debit($store, $wallet, $amount, 'amount', $module);

            return StoreExpense::create([
                'store_id' => $store->id,
                'module' => $module,
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
        ?string $module = null,
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

        $module = $this->resolveModule($module);

        return DB::transaction(function () use ($store, $amount, $wallet, $kind, $receivedOn, $narration, $user, $module) {
            $this->credit($store, $wallet, $amount, $module);

            if ($kind === 'investment') {
                $locked = $this->lock($store);
                $capital = $this->columns($module)['capital'];
                $locked->{$capital} = round((float) $locked->{$capital} + $amount, 2);
                $locked->save();
                $store->refresh();
            }

            return StoreIncome::create([
                'store_id' => $store->id,
                'module' => $module,
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

    /**
     * @return array{capital: string, cash: string, bank: string, set_at: string}
     */
    public function columns(string $module): array
    {
        if ($this->resolveModule($module) === self::GIRVI) {
            return [
                'capital' => 'girvi_opening_capital',
                'cash' => 'girvi_cash_in_hand',
                'bank' => 'girvi_bank_balance',
                'set_at' => 'girvi_books_set_at',
            ];
        }

        return [
            'capital' => 'opening_capital',
            'cash' => 'cash_in_hand',
            'bank' => 'bank_balance',
            'set_at' => 'books_set_at',
        ];
    }

    private function lock(Store $store): Store
    {
        return Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
    }

    private function column(string $wallet, string $module): string
    {
        $columns = $this->columns($module);

        return $wallet === 'bank' ? $columns['bank'] : $columns['cash'];
    }

    private function label(string $wallet, string $module): string
    {
        $book = $module === self::GIRVI ? 'girvi ' : '';

        return $wallet === 'bank' ? 'the '.$book.'bank' : $book.'cash in hand';
    }
}
