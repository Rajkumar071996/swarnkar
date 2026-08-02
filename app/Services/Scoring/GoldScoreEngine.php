<?php

namespace App\Services\Scoring;

use App\Enums\RiskBand;
use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Combines the weighted components into a single 300-900 score.
 *
 * Components with no data are dropped and the surviving weights renormalised,
 * so a customer who has only ever taken store credit is judged on that rather
 * than being marked down for never having pledged gold. A customer with no
 * history anywhere comes back unscored rather than as a 300.
 */
class GoldScoreEngine
{
    /** @var array<int, ComponentCalculator> */
    private array $calculators;

    public function __construct(
        UdhaarSettlementCalculator $udhaar,
        GoldLoanRepaymentCalculator $goldLoan,
        DefaultFlagCalculator $flags,
        private readonly CreditLimitAdvisor $creditLimitAdvisor,
    ) {
        $this->calculators = [$udhaar, $goldLoan, $flags];
    }

    public function score(Customer $customer, ?Carbon $asOf = null): ScoreResult
    {
        $asOf = $asOf ? $asOf->copy() : Carbon::now();

        $components = array_map(
            fn (ComponentCalculator $calculator) => $calculator->calculate($customer, $asOf),
            $this->calculators
        );

        $scored = array_filter($components, fn (ScoreComponent $c) => $c->hasData());
        $observations = array_sum(array_map(fn (ScoreComponent $c) => $c->observations, $components));

        if ($scored === []) {
            return new ScoreResult(
                score: null,
                band: RiskBand::Unscored,
                components: $components,
                recommendedCreditLimit: 0.0,
                observationCount: 0,
                computedAt: $asOf,
            );
        }

        $weightTotal = array_sum(array_map(fn (ScoreComponent $c) => $c->weight, $scored));
        $pointsTotal = array_sum(array_map(fn (ScoreComponent $c) => $c->points(), $scored));

        $ratio = $weightTotal > 0 ? $pointsTotal / $weightTotal : 0.0;
        $ratio = max(0.0, min(1.0, $ratio));

        $min = (int) config('goldscore.range.min');
        $max = (int) config('goldscore.range.max');
        $score = (int) round($min + ($max - $min) * $ratio);

        $band = RiskBand::forScore($score);

        return new ScoreResult(
            score: $score,
            band: $band,
            components: $components,
            recommendedCreditLimit: $this->creditLimitAdvisor->recommend($customer, $band, $asOf),
            observationCount: $observations,
            computedAt: $asOf,
        );
    }
}
