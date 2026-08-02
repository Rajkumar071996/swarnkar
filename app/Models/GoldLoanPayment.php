<?php

namespace App\Models;

use Database\Factories\GoldLoanPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['gold_loan_id', 'amount', 'paid_on', 'method'])]
class GoldLoanPayment extends Model
{
    /** @use HasFactory<GoldLoanPaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function goldLoan(): BelongsTo
    {
        return $this->belongsTo(GoldLoan::class);
    }
}
