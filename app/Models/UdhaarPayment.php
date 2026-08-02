<?php

namespace App\Models;

use Database\Factories\UdhaarPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['udhaar_id', 'amount', 'paid_on', 'method', 'reference', 'recorded_by_user_id'])]
class UdhaarPayment extends Model
{
    /** @use HasFactory<UdhaarPaymentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function udhaar(): BelongsTo
    {
        return $this->belongsTo(Udhaar::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
