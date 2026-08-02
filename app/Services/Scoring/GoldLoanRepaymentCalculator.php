<?php

namespace App\Services\Scoring;

use App\Enums\GoldLoanStatus;
use App\Models\Customer;
use App\Models\GoldLoan;
use Illuminate\Support\Carbon;

/**
 * 15% of the score: how pledged gold loans were closed out. Letting a pledge go
 * to auction is the strongest negative signal in the dataset, since the
 * customer preferred losing the metal to repaying.
 */
class GoldLoanRepaymentCalculator implements ComponentCalculator
{
    public function key(): string
    {
        return 'gold_loan';
    }

    public function calculate(Customer $customer, Carbon $asOf): ScoreComponent
    {
        $weight = (float) config('goldscore.weights.gold_loan');

        $loans = GoldLoan::query()
            ->networkWide()
            ->where('customer_id', $customer->id)
            ->get();

        if ($loans->isEmpty()) {
            return ScoreComponent::noData($this->key(), 'Pledged gold loan repayment', $weight);
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $observations = 0;
        $counts = ['closed' => 0, 'renewed' => 0, 'auctioned' => 0, 'overdue' => 0];

        foreach ($loans as $loan) {
            $credit = $this->creditFor($loan, $asOf, $counts);

            if ($credit === null) {
                continue;
            }

            $recency = RecencyWeight::for($loan->disbursed_on, $asOf);

            if ($recency <= 0.0) {
                continue;
            }

            $observationWeight = $recency * max(1000.0, (float) $loan->principal_amount);
            $weightedSum += $credit * $observationWeight;
            $weightTotal += $observationWeight;
            $observations++;
        }

        if ($weightTotal <= 0.0) {
            return ScoreComponent::noData($this->key(), 'Pledged gold loan repayment', $weight);
        }

        return new ScoreComponent(
            key: $this->key(),
            label: 'Pledged gold loan repayment',
            ratio: $weightedSum / $weightTotal,
            weight: $weight,
            observations: $observations,
            detail: [
                'loans' => $loans->count(),
                'closed' => $counts['closed'],
                'renewed' => $counts['renewed'],
                'auctioned' => $counts['auctioned'],
                'currently_overdue' => $counts['overdue'],
            ],
        );
    }

    private function creditFor(GoldLoan $loan, Carbon $asOf, array &$counts): ?float
    {
        $graceDays = (int) config('goldscore.gold_loan.grace_days');

        if ($loan->status === GoldLoanStatus::Auctioned) {
            $counts['auctioned']++;

            return (float) config('goldscore.gold_loan.auctioned_credit');
        }

        if ($loan->status === GoldLoanStatus::Renewed) {
            $counts['renewed']++;

            return (float) config('goldscore.gold_loan.renewed_credit');
        }

        if ($loan->status === GoldLoanStatus::Closed) {
            $counts['closed']++;
            $closedOn = $loan->closed_on ?? $asOf;
            $deadline = $loan->due_on->copy()->addDays($graceDays);

            return $closedOn->lessThanOrEqualTo($deadline)
                ? (float) config('goldscore.gold_loan.closed_on_time_credit')
                : (float) config('goldscore.gold_loan.closed_late_credit');
        }

        if ($asOf->greaterThan($loan->due_on->copy()->addDays($graceDays))) {
            $counts['overdue']++;

            return (float) config('goldscore.gold_loan.open_overdue_credit');
        }

        // Still running and not yet due: nothing to conclude.
        return null;
    }
}
