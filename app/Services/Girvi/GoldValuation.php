<?php

namespace App\Services\Girvi;

/**
 * The arithmetic behind the pledge entry screen, kept in one place so the form,
 * the stored loan and the printed receipt can never disagree.
 *
 * Net weight is what is left after stones, fine weight is the pure metal in it,
 * and the estimate is the share of that value the shop is willing to lend.
 */
class GoldValuation
{
    public function netWeight(float $gross, float $less): float
    {
        return round(max(0, $gross - $less), 3);
    }

    public function fineWeight(float $net, float $weightPercent): float
    {
        return round($net * $weightPercent / 100, 3);
    }

    public function amount(float $fineWeight, float $ratePerGram): float
    {
        return round($fineWeight * $ratePerGram, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function priceItems(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $gross = (float) ($row['gross_weight_grams'] ?? 0);
            $less = (float) ($row['less_weight_grams'] ?? 0);
            $percent = (float) ($row['weight_percent'] ?? 100);
            $rate = (float) ($row['rate_per_gram'] ?? 0);

            $net = $this->netWeight($gross, $less);
            $fine = $this->fineWeight($net, $percent);

            $items[] = [
                'metal_type' => $row['metal_type'] ?? 'gold',
                'item_type' => $row['item_type'] ?? '',
                'quantity' => (int) ($row['quantity'] ?? 1),
                'gross_weight_grams' => round($gross, 3),
                'less_weight_grams' => round($less, 3),
                'net_weight_grams' => $net,
                'weight_percent' => round($percent, 2),
                'fine_weight_grams' => $fine,
                'rate_per_gram' => round($rate, 2),
                'total_amount' => $this->amount($fine, $rate),
                'remarks' => $row['remarks'] ?? null,
            ];
        }

        return [
            'items' => $items,
            'gross_weight_grams' => round(array_sum(array_column($items, 'gross_weight_grams')), 3),
            'less_weight_grams' => round(array_sum(array_column($items, 'less_weight_grams')), 3),
            'net_weight_grams' => round(array_sum(array_column($items, 'net_weight_grams')), 3),
            'fine_weight_grams' => round(array_sum(array_column($items, 'fine_weight_grams')), 3),
            'total_value' => round(array_sum(array_column($items, 'total_amount')), 2),
        ];
    }

    public function estimateAmount(float $totalValue, float $estimatePercent): float
    {
        return round($totalValue * $estimatePercent / 100, 2);
    }
}
