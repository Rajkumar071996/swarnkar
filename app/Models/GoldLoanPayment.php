<?php

namespace App\Models;

use Database\Factories\GoldLoanPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gold_loan_id', 'amount', 'type', 'receipt_no', 'penalty', 'discount',
    'paid_on', 'method', 'reference', 'notes', 'recorded_by_user_id',
])]
class GoldLoanPayment extends Model
{
    /** @use HasFactory<GoldLoanPaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'penalty' => 'decimal:2',
            'discount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function goldLoan(): BelongsTo
    {
        return $this->belongsTo(GoldLoan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
