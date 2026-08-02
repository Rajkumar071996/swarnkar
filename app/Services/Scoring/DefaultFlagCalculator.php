<?php

namespace App\Services\Scoring;

use App\Models\Customer;
use App\Models\DefaultFlag;
use Illuminate\Support\Carbon;

/**
 * 15% of the score, and a penalty channel only.
 *
 * The absence of flags contributes nothing rather than a free 15%: a stranger
 * with no record must not be handed the same credit as someone with a proven
 * clean history. When flags do exist, the component drags the score down.
 * Only verified flags count, so a competitor cannot damage a rating by filing
 * an unsupported report.
 */
class DefaultFlagCalculator implements ComponentCalculator
{
    public function key(): string
    {
        return 'flags';
    }

    public function calculate(Customer $customer, Carbon $asOf): ScoreComponent
    {
        $weight = (float) config('goldscore.weights.flags');

        $flags = DefaultFlag::query()
            ->networkWide()
            ->where('customer_id', $customer->id)
            ->verified()
            ->get();

        if ($flags->isEmpty()) {
            return ScoreComponent::noData($this->key(), 'Merchant default reports', $weight, [
                'verified_flags' => 0,
            ]);
        }

        $decayAfter = (int) config('goldscore.flags.decay_after_months');
        $decayedMultiplier = (float) config('goldscore.flags.decayed_multiplier');

        $deduction = 0.0;
        $reasons = [];

        foreach ($flags as $flag) {
            $monthsAgo = max(0, $flag->occurred_on->diffInMonths($asOf));

            if ($monthsAgo > (int) config('goldscore.recency.lookback_months')) {
                continue;
            }

            $severity = $flag->reason->severity();
            $deduction += $monthsAgo > $decayAfter ? $severity * $decayedMultiplier : $severity;
            $reasons[] = $flag->reason->label();
        }

        if ($reasons === []) {
            return ScoreComponent::noData($this->key(), 'Merchant default reports', $weight, [
                'verified_flags' => 0,
            ]);
        }

        return new ScoreComponent(
            key: $this->key(),
            label: 'Merchant default reports',
            ratio: max(0.0, 1.0 - $deduction),
            weight: $weight,
            observations: count($reasons),
            detail: [
                'verified_flags' => count($reasons),
                'reasons' => array_values(array_unique($reasons)),
                'total_amount_involved' => round((float) $flags->sum('amount_involved'), 2),
            ],
        );
    }
}
