<?php

namespace App\Models;

use App\Enums\RiskBand;
use Database\Factories\ScoreSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id', 'score', 'band', 'breakdown', 'recommended_credit_limit',
    'observation_count', 'algorithm_version', 'computed_at',
])]
class ScoreSnapshot extends Model
{
    /** @use HasFactory<ScoreSnapshotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'breakdown' => 'array',
            'recommended_credit_limit' => 'decimal:2',
            'band' => RiskBand::class,
            'computed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Fraction of the 300-900 range the score occupies, used to sweep the dial.
     */
    public function dialFraction(): float
    {
        if ($this->score === null) {
            return 0.0;
        }

        $min = (int) config('goldscore.range.min');
        $max = (int) config('goldscore.range.max');

        return max(0.0, min(1.0, ($this->score - $min) / ($max - $min)));
    }
}
