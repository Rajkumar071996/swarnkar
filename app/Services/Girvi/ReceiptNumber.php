<?php

namespace App\Services\Girvi;

use Illuminate\Support\Facades\DB;

/**
 * Issues the counter's receipt numbers: GRT-19/27-4 when jewellery is taken in
 * and GRS-19/27-17 when it goes back out. The serial runs per store and per
 * book, so the numbers a shop reads out never depend on another shop.
 */
class ReceiptNumber
{
    public function forDeposit(int $storeId): string
    {
        $stem = $this->stem(config('girvi.receipt.deposit_prefix'));

        $existing = DB::table('gold_loans')
            ->where('store_id', $storeId)
            ->where('receipt_no', 'like', $stem.'%')
            ->pluck('receipt_no');

        return $stem.$this->nextSerial($existing->all(), $stem);
    }

    public function forRelease(int $storeId): string
    {
        $stem = $this->stem(config('girvi.receipt.release_prefix'));

        $existing = DB::table('gold_loan_payments')
            ->join('gold_loans', 'gold_loans.id', '=', 'gold_loan_payments.gold_loan_id')
            ->where('gold_loans.store_id', $storeId)
            ->where('gold_loan_payments.receipt_no', 'like', $stem.'%')
            ->pluck('gold_loan_payments.receipt_no');

        return $stem.$this->nextSerial($existing->all(), $stem);
    }

    /**
     * The internal mortgage number. Store scoped so it stays unique across the
     * network without leaking another shop's volume.
     */
    public function forLoan(int $storeId): string
    {
        $stem = 'M-'.$storeId.'-';

        $existing = DB::table('gold_loans')
            ->where('store_id', $storeId)
            ->where('loan_no', 'like', $stem.'%')
            ->pluck('loan_no');

        return $stem.$this->nextSerial($existing->all(), $stem);
    }

    private function stem(string $prefix): string
    {
        return $prefix.'-'.config('girvi.receipt.book_code').'-';
    }

    /**
     * Serials are compared in PHP rather than in SQL so the same code works on
     * both MariaDB and the SQLite the tests run against.
     *
     * @param  array<int, string|null>  $existing
     */
    private function nextSerial(array $existing, string $stem): int
    {
        $highest = 0;

        foreach ($existing as $value) {
            $suffix = substr((string) $value, strlen($stem));

            if (ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $highest + 1;
    }
}
