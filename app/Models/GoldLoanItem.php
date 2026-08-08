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
}
