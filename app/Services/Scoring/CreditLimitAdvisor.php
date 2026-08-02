<?php

namespace App\Services\Scoring;

use App\Enums\RiskBand;
use App\Enums\UdhaarStatus;
use App\Models\Customer;
use App\Models\Udhaar;
use App\Services\CreditExposure;
use Illuminate\Support\Carbon;

/**
 * Turns a band into a rupee figure. The band says how likely someone is to pay;
 * capacity says how much they have actually handled before. Recommending more
 * than a customer has ever repaid would be a guess dressed up as a number.
 *
 * Whatever they already owe elsewhere is then subtracted, so the number on
 * screen is what this shop can safely add today rather than a total the
 * customer may already have used up somewhere else.
 */
class CreditLimitAdvisor
{
    public function __construct(private readonly CreditExposure $exposure) {}

    public function recommend(Customer $customer, RiskBand $band, Carbon $asOf): float
    {
        $ceiling = $this->capacityCeiling($customer, $band, $asOf);

        if ($ceiling <= 0.0) {
            return 0.0;
        }

        $outstanding = $this->exposure->for($customer, null, $asOf)->total;
        $tolerance = (float) config('goldscore.credit_limit.exposure_tolerance');
        $headroom = $ceiling - max(0.0, $outstanding - $tolerance);

        if ($headroom <= 0.0) {
            return 0.0;
        }

        return $this->round($headroom);
    }

    /**
     * What the customer could carry in total if they owed nothing right now.
     * Exposed separately so the report can show the deduction rather than just
     * a shrunken number with no explanation.
     */
    public function capacityCeiling(Customer $customer, RiskBand $band, Carbon $asOf): float
    {
        $multiplier = (float) (config('goldscore.credit_limit.multipliers')[$band->value] ?? 0.0);

        if ($multiplier <= 0.0) {
            return 0.0;
        }

        $capacity = $this->largestSettledUdhaar($customer, $asOf);

        if ($capacity <= 0.0) {
            return 0.0;
        }

        return $this->round($capacity * $multiplier);
    }

    private function round(float $amount): float
    {
        $rounding = max(1, (int) config('goldscore.credit_limit.rounding'));
        $rounded = floor($amount / $rounding) * $rounding;

        return (float) min($rounded, (int) config('goldscore.credit_limit.ceiling'));
    }

    /**
     * The largest single account they have cleared recently. Anything older than
     * two years says more about who they used to be than who they are now.
     */
    private function largestSettledUdhaar(Customer $customer, Carbon $asOf): float
    {
        return (float) Udhaar::query()
            ->networkWide()
            ->where('customer_id', $customer->id)
            ->where('status', UdhaarStatus::Settled->value)
            ->whereDate('issued_on', '>=', $asOf->copy()->subMonths(24))
            ->max('principal_amount');
    }
}
