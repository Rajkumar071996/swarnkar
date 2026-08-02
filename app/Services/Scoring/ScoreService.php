<?php

namespace App\Services\Scoring;

use App\Models\Customer;
use App\Models\ScoreSnapshot;
use Illuminate\Support\Carbon;

/**
 * Materialises scores so a credit check at the counter is one indexed read
 * rather than a fan-out across every ledger table. Snapshots accumulate as a
 * score history instead of being overwritten.
 */
class ScoreService
{
    public function __construct(private readonly GoldScoreEngine $engine) {}

    public function refresh(Customer $customer, ?Carbon $asOf = null): ScoreSnapshot
    {
        $result = $this->engine->score($customer, $asOf);

        return ScoreSnapshot::create($result->toSnapshotAttributes($customer->id));
    }

    /**
     * The snapshot to show a jeweller, computing one on first sight so a lookup
     * never dead-ends on a customer whose score has not been built yet.
     */
    public function current(Customer $customer): ScoreSnapshot
    {
        return $customer->latestScore ?? $this->refresh($customer);
    }

    public function refreshMany(iterable $customers): int
    {
        $count = 0;

        foreach ($customers as $customer) {
            $this->refresh($customer);
            $count++;
        }

        return $count;
    }
}
