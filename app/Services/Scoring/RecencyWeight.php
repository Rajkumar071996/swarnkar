<?php

namespace App\Services\Scoring;

use Illuminate\Support\Carbon;

/**
 * How much an observation still counts for. A default from four years ago says
 * far less about someone today than one from last Diwali, so weight decays with
 * a half-life rather than dropping off a cliff at an arbitrary cutoff.
 */
class RecencyWeight
{
    public static function for(Carbon $observedOn, Carbon $asOf): float
    {
        $monthsAgo = max(0, $observedOn->diffInMonths($asOf));

        if ($monthsAgo > (int) config('goldscore.recency.lookback_months')) {
            return 0.0;
        }

        $halfLife = max(1, (int) config('goldscore.recency.half_life_months'));
        $weight = 0.5 ** ($monthsAgo / $halfLife);

        return max((float) config('goldscore.recency.min_weight'), $weight);
    }
}
