<?php

namespace App\Models;

use App\Enums\UdhaarStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\UdhaarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'store_id', 'customer_id', 'invoice_no', 'item_description', 'principal_amount',
    'collateral_description', 'collateral_weight_grams', 'issued_on', 'due_on',
    'notes', 'created_by_user_id',
])]
class Udhaar extends Model
{
    use BelongsToStore;

    /** @use HasFactory<UdhaarFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'collateral_weight_grams' => 'decimal:3',
            'issued_on' => 'date',
            'due_on' => 'date',
            'settled_on' => 'date',
            'status' => UdhaarStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(UdhaarPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function outstandingAmount(): float
    {
        return round((float) $this->principal_amount - (float) $this->amount_paid, 2);
    }

    public function isFullySettled(): bool
    {
        // Tolerance covers rounding on part payments rather than exact equality.
        return $this->outstandingAmount() <= 0.009;
    }

    public function daysOverdue(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::today();
        $reference = $this->settled_on ?? $asOf;

        return $reference->greaterThan($this->due_on) ? $this->due_on->diffInDays($reference) : 0;
    }

    /**
     * Derives status from the payment total and the calendar so it can never
     * disagree with the ledger rows underneath it.
     */
    public function syncStatus(?Carbon $asOf = null): void
    {
        if ($this->status === UdhaarStatus::WrittenOff) {
            return;
        }

        $asOf = $asOf ?? Carbon::today();
        $paid = (float) $this->payments()->sum('amount');
        $this->amount_paid = $paid;

        if ($this->isFullySettled()) {
            $this->status = UdhaarStatus::Settled;
            $this->settled_on = $this->settled_on ?? $this->payments()->max('paid_on') ?? $asOf;

            return;
        }

        $this->settled_on = null;
        $overdueDays = $asOf->greaterThan($this->due_on) ? $this->due_on->diffInDays($asOf) : 0;

        $this->status = match (true) {
            $overdueDays > config('goldscore.udhaar.default_days') => UdhaarStatus::Defaulted,
            $paid > 0 => UdhaarStatus::PartiallyPaid,
            default => UdhaarStatus::Open,
        };
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            UdhaarStatus::Open->value,
            UdhaarStatus::PartiallyPaid->value,
            UdhaarStatus::Defaulted->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_on', '<', Carbon::today());
    }
}
