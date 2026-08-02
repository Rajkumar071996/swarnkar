<?php

namespace App\Services\Scoring;

use App\Enums\UdhaarStatus;
use App\Models\Customer;
use App\Models\Udhaar;
use Illuminate\Support\Carbon;

/**
 * 30% of the score: how promptly store credit gets cleared. Observations are
 * weighted by amount as well as recency, because clearing a ten-lakh account is
 * a far stronger signal than clearing a two-thousand-rupee one.
 */
class UdhaarSettlementCalculator implements ComponentCalculator
{
    public function key(): string
    {
        return 'udhaar';
    }

    public function calculate(Customer $customer, Carbon $asOf): ScoreComponent
    {
        $weight = (float) config('goldscore.weights.udhaar');

        $udhaars = Udhaar::query()
            ->networkWide()
            ->where('customer_id', $customer->id)
            ->get();

        if ($udhaars->isEmpty()) {
            return ScoreComponent::noData($this->key(), 'Store credit settlement', $weight);
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $settledOnTime = $settledLate = $currentlyOverdue = $writtenOff = 0;
        $observations = 0;

        foreach ($udhaars as $udhaar) {
            $credit = $this->creditFor($udhaar, $asOf);

            if ($credit === null) {
                continue;
            }

            $recency = RecencyWeight::for($udhaar->issued_on, $asOf);

            if ($recency <= 0.0) {
                continue;
            }

            // Amount weighting uses the principal directly, floored so a token
            // account still registers rather than vanishing from the average.
            $amountWeight = max(1000.0, (float) $udhaar->principal_amount);
            $observationWeight = $recency * $amountWeight;

            $weightedSum += $credit * $observationWeight;
            $weightTotal += $observationWeight;
            $observations++;

            match (true) {
                $udhaar->status === UdhaarStatus::WrittenOff => $writtenOff++,
                $udhaar->status === UdhaarStatus::Settled && $credit >= 1.0 => $settledOnTime++,
                $udhaar->status === UdhaarStatus::Settled => $settledLate++,
                default => $currentlyOverdue++,
            };
        }

        if ($weightTotal <= 0.0) {
            return ScoreComponent::noData($this->key(), 'Store credit settlement', $weight);
        }

        return new ScoreComponent(
            key: $this->key(),
            label: 'Store credit settlement',
            ratio: $weightedSum / $weightTotal,
            weight: $weight,
            observations: $observations,
            detail: [
                'accounts' => $udhaars->count(),
                'settled_on_time' => $settledOnTime,
                'settled_late' => $settledLate,
                'currently_overdue' => $currentlyOverdue,
                'written_off' => $writtenOff,
                'outstanding_amount' => round($udhaars->filter(
                    fn (Udhaar $u) => $u->status->isOutstanding()
                )->sum(fn (Udhaar $u) => $u->outstandingAmount()), 2),
            ],
        );
    }

    /**
     * Returns null for accounts that carry no repayment signal yet, such as an
     * open account that has not reached its due date.
     */
    private function creditFor(Udhaar $udhaar, Carbon $asOf): ?float
    {
        if ($udhaar->status === UdhaarStatus::WrittenOff) {
            return 0.0;
        }

        if ($udhaar->status === UdhaarStatus::Settled) {
            $settledOn = $udhaar->settled_on ?? $asOf;

            return $this->creditForDaysLate(
                $settledOn->greaterThan($udhaar->due_on) ? $udhaar->due_on->diffInDays($settledOn) : 0
            );
        }

        $daysOverdue = $asOf->greaterThan($udhaar->due_on) ? $udhaar->due_on->diffInDays($asOf) : 0;

        if ($daysOverdue <= 0) {
            return null;
        }

        if ($daysOverdue > (int) config('goldscore.udhaar.open_overdue_zero_days')) {
            return 0.0;
        }

        return $this->creditForDaysLate($daysOverdue);
    }

    private function creditForDaysLate(int $daysLate): float
    {
        foreach (config('goldscore.udhaar.settlement_tiers') as $tier) {
            if ($daysLate <= (int) $tier['days_late']) {
                return (float) $tier['credit'];
            }
        }

        return (float) config('goldscore.udhaar.beyond_tier_credit');
    }
}
