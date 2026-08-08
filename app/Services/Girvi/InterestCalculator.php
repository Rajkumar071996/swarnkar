<?php

namespace App\Services\Girvi;

use App\Models\GoldLoan;
use Illuminate\Support\Carbon;

/**
 * Simple interest on a pledge, quoted as an annual percentage and charged by
 * the month. A part month counts as a whole one, and a pledge released the same
 * day still carries a month, which is how the counter has always billed it.
 */
class InterestCalculator
{
    public function chargeableMonths(Carbon $from, Carbon $to): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 1;
        }

        $months = (int) $from->diffInMonths($to);

        if ($from->copy()->addMonths($months)->lessThan($to)) {
            $months++;
        }

        return max(1, $months);
    }

    public function interest(float $principal, float $annualRate, Carbon $from, Carbon $to): float
    {
        $months = $this->chargeableMonths($from, $to);

        return round($principal * ($annualRate / 100) * ($months / 12), 2);
    }

    /**
     * Everything the release screen needs to show: interest run to the release
     * date, what has already been collected, and the cash to take today.
     *
     * @param  array<string, float>  $charges
     * @return array<string, mixed>
     */
    public function releaseSummary(GoldLoan $loan, Carbon $releaseOn, array $charges = []): array
    {
        $principal = $loan->outstandingPrincipal();
        $interestDue = $this->interest(
            (float) $loan->principal_amount,
            (float) $loan->interest_rate,
            $loan->disbursed_on,
            $releaseOn,
        );

        $interestPaid = (float) $loan->interest_collected;
        $extraAmount = round((float) ($charges['extra_amount'] ?? 0), 2);
        $extraInterest = round((float) ($charges['extra_interest'] ?? 0), 2);
        $noticeCharge = round((float) ($charges['notice_charge'] ?? 0), 2);
        $discount = round((float) ($charges['discount'] ?? 0), 2);

        $interestPayable = round(max(0, $interestDue - $interestPaid), 2);

        return [
            'months' => $this->chargeableMonths($loan->disbursed_on, $releaseOn),
            'principal' => $principal,
            'interest_due' => $interestDue,
            'interest_paid' => $interestPaid,
            'interest_payable' => $interestPayable,
            'extra_amount' => $extraAmount,
            'extra_interest' => $extraInterest,
            'notice_charge' => $noticeCharge,
            'discount' => $discount,
            'total' => round(
                $principal + $interestPayable + $extraAmount + $extraInterest + $noticeCharge - $discount,
                2
            ),
        ];
    }
}
