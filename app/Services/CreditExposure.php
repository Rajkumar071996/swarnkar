<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Udhaar;
use App\Support\CreditExposureSummary;
use Illuminate\Support\Carbon;

/**
 * Answers the question the whole product exists to answer: how much does this
 * person already owe, and to how many other jewellers?
 *
 * A customer can be spotless in your own khata and still be carrying unpaid
 * credit at three shops down the road. Reads across every store, deliberately,
 * because that shared view is the point.
 */
class CreditExposure
{
    public function for(Customer $customer, ?int $viewingStoreId = null, ?Carbon $asOf = null): CreditExposureSummary
    {
        $asOf = $asOf ? $asOf->copy() : Carbon::today();

        $accounts = Udhaar::query()
            ->networkWide()
            ->where('customer_id', $customer->id)
            ->outstanding()
            ->get();

        if ($accounts->isEmpty()) {
            return CreditExposureSummary::empty();
        }

        $storeNames = Store::query()
            ->whereIn('id', $accounts->pluck('store_id')->unique())
            ->get()
            ->keyBy('id');

        $total = $own = $elsewhere = $overdue = 0.0;
        $oldestOverdueDays = 0;
        $byStore = [];

        foreach ($accounts as $account) {
            $outstanding = $account->outstandingAmount();

            if ($outstanding <= 0.0) {
                continue;
            }

            $isOwnStore = $viewingStoreId !== null && $account->store_id === $viewingStoreId;
            $daysOverdue = $asOf->greaterThan($account->due_on)
                ? (int) $account->due_on->diffInDays($asOf)
                : 0;

            $total += $outstanding;
            $isOwnStore ? $own += $outstanding : $elsewhere += $outstanding;

            if ($daysOverdue > 0) {
                $overdue += $outstanding;
                $oldestOverdueDays = max($oldestOverdueDays, $daysOverdue);
            }

            $key = $account->store_id;
            $byStore[$key] ??= [
                // The shop asking is named; everyone else is a city, so a lookup
                // cannot be used to map a competitor's customer book.
                'label' => $isOwnStore
                    ? 'Your store'
                    : ($storeNames[$key]?->anonymisedLabel() ?? 'Another jeweller'),
                'outstanding' => 0.0,
                'overdue' => 0.0,
                'own_store' => $isOwnStore,
            ];

            $byStore[$key]['outstanding'] += $outstanding;
            $byStore[$key]['overdue'] += $daysOverdue > 0 ? $outstanding : 0.0;
        }

        $stores = collect($byStore)
            ->map(fn (array $row) => [
                ...$row,
                'outstanding' => round($row['outstanding'], 2),
                'overdue' => round($row['overdue'], 2),
            ])
            ->sortByDesc('outstanding')
            ->values();

        return new CreditExposureSummary(
            total: round($total, 2),
            ownStore: round($own, 2),
            elsewhere: round($elsewhere, 2),
            overdue: round($overdue, 2),
            oldestOverdueDays: $oldestOverdueDays,
            storeCount: $stores->count(),
            elsewhereStoreCount: $stores->where('own_store', false)->count(),
            stores: $stores,
        );
    }

    /**
     * Just the rupee figure, for callers that do not need the breakdown.
     */
    public function outstandingTotal(Customer $customer, ?Carbon $asOf = null): float
    {
        return $this->for($customer, null, $asOf)->total;
    }
}
