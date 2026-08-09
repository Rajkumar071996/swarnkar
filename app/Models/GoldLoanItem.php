<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gold_loan_id', 'metal_type', 'item_type', 'quantity',
    'gross_weight_grams', 'less_weight_grams', 'net_weight_grams',
    'weight_percent', 'fine_weight_grams', 'rate_per_gram', 'total_amount', 'remarks',
])]
class GoldLoanItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'gross_weight_grams' => 'decimal:3',
            'less_weight_grams' => 'decimal:3',
            'net_weight_grams' => 'decimal:3',
            'weight_percent' => 'decimal:2',
            'fine_weight_grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function goldLoan(): BelongsTo
    {
        return $this->belongsTo(GoldLoan::class);
    }

    /**
     * Fine metal sitting in the shop right now, split by metal. Gold and silver
     * are worth an order of magnitude apart, so a single combined weight tells
     * the owner very little about what is actually in the safe.
     *
     * @return array<string, float>
     */
    public static function fineWeightHeld(): array
    {
        $held = static::query()
            ->whereIn('gold_loan_id', GoldLoan::query()->unreleased()->select('id'))
            ->groupBy('metal_type')
            ->selectRaw('metal_type, SUM(fine_weight_grams) AS fine')
            ->pluck('fine', 'metal_type');

        $weights = [];

        foreach (array_keys(config('girvi.metal_types')) as $metal) {
            $weights[$metal] = round((float) ($held[$metal] ?? 0), 3);
        }

        return $weights;
    }
}
