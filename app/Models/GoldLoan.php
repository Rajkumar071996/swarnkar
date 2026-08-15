<?php

namespace App\Models;

use App\Enums\GoldLoanStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\GoldLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'store_id', 'customer_id', 'loan_no', 'invoice_no', 'receipt_no', 'packet_no', 'barcode',
    'principal_amount', 'interest_rate', 'duration_months',
    'pledged_weight_grams', 'purity_karat',
    'gross_weight_grams', 'less_weight_grams', 'net_weight_grams', 'fine_weight_grams',
    'rate_per_gram', 'total_value', 'estimate_percent', 'estimate_amount',
    'interest_collected', 'principal_repaid', 'extra_amount', 'notice_charge', 'discount',
    'loan_reason', 'loan_type', 'refer_by', 'narration',
    'disbursed_on', 'due_on', 'closed_on', 'released_on', 'status', 'created_by_user_id',
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
            'duration_months' => 'integer',
            'pledged_weight_grams' => 'decimal:3',
            'gross_weight_grams' => 'decimal:3',
            'less_weight_grams' => 'decimal:3',
            'net_weight_grams' => 'decimal:3',
            'fine_weight_grams' => 'decimal:3',
            'rate_per_gram' => 'decimal:2',
            'total_value' => 'decimal:2',
            'estimate_percent' => 'decimal:2',
            'estimate_amount' => 'decimal:2',
            'interest_collected' => 'decimal:2',
            'principal_repaid' => 'decimal:2',
            'extra_amount' => 'decimal:2',
            'notice_charge' => 'decimal:2',
            'discount' => 'decimal:2',
            'disbursed_on' => 'date',
            'due_on' => 'date',
            'closed_on' => 'date',
            'released_on' => 'date',
            'status' => GoldLoanStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoldLoanItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GoldLoanPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Pledges still with the shop, whatever their due date. Loans that were
     * renewed or auctioned never come back out, so they are not held either.
     */
    public function scopeUnreleased(Builder $query): Builder
    {
        return $query->whereNull('released_on')->where('status', GoldLoanStatus::Active);
    }

    public function scopeReleased(Builder $query): Builder
    {
        return $query->whereNotNull('released_on');
    }

    public function scopeOverdue(Builder $query, ?Carbon $asOf = null): Builder
    {
        return $query->unreleased()->whereDate('due_on', '<', $asOf ?? Carbon::today());
    }

    public function outstandingPrincipal(): float
    {
        return round((float) $this->principal_amount - (float) $this->principal_repaid, 2);
    }

    /**
     * The counter quotes interest by the month. The column still stores the
     * annual figure the calculator uses, so a 5% monthly rate is 60 here.
     */
    public function monthlyInterestRate(): float
    {
        return round((float) $this->interest_rate / 12, 2);
    }

    public function isReleased(): bool
    {
        return $this->released_on !== null;
    }

    public function daysOverdue(?Carbon $asOf = null): int
    {
        $asOf = $asOf ?? Carbon::today();
        $reference = $this->released_on ?? $asOf;

        return $reference->greaterThan($this->due_on) ? (int) $this->due_on->diffInDays($reference) : 0;
    }
}
