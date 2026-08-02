<?php

namespace App\Models;

use App\Enums\GoldLoanStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\GoldLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'store_id', 'customer_id', 'loan_no', 'principal_amount', 'interest_rate',
    'pledged_weight_grams', 'purity_karat', 'disbursed_on', 'due_on', 'closed_on', 'status',
])]
class GoldLoan extends Model
{
    use BelongsToStore;

    /** @use HasFactory<GoldLoanFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'pledged_weight_grams' => 'decimal:3',
            'disbursed_on' => 'date',
            'due_on' => 'date',
            'closed_on' => 'date',
            'status' => GoldLoanStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GoldLoanPayment::class);
    }
}
