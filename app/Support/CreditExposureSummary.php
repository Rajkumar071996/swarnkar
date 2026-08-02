<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * What a customer currently owes across the whole merchant network, split into
 * what the shop asking already knows about and what it does not.
 *
 * The second figure is the one that matters at the counter: a customer can look
 * spotless in your own book and still be carrying two lakh elsewhere.
 */
class CreditExposureSummary
{
    /**
     * @param  Collection<int, array{label: string, outstanding: float, overdue: float, own_store: bool}>  $stores
     */
    public function __construct(
        public readonly float $total,
        public readonly float $ownStore,
        public readonly float $elsewhere,
        public readonly float $overdue,
        public readonly int $oldestOverdueDays,
        public readonly int $storeCount,
        public readonly int $elsewhereStoreCount,
        public readonly Collection $stores,
    ) {}

    public static function empty(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, 0, 0, 0, collect());
    }

    public function hasExposure(): bool
    {
        return $this->total > 0.0;
    }

    /**
     * The case the product exists for: clean with us, owing somewhere else.
     */
    public function hasHiddenExposure(): bool
    {
        return $this->elsewhere > 0.0;
    }

    public function hasOverdue(): bool
    {
        return $this->overdue > 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_outstanding' => $this->total,
            'at_your_store' => $this->ownStore,
            'at_other_stores' => $this->elsewhere,
            'overdue_amount' => $this->overdue,
            'oldest_overdue_days' => $this->oldestOverdueDays,
            'store_count' => $this->storeCount,
            'other_store_count' => $this->elsewhereStoreCount,
            'stores' => $this->stores->values()->all(),
        ];
    }
}
