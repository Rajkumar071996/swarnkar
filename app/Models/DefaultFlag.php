<?php

namespace App\Models;

use App\Enums\DefaultFlagReason;
use App\Enums\DefaultFlagStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\DefaultFlagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'store_id', 'customer_id', 'reason', 'status', 'amount_involved',
    'narrative', 'evidence_path', 'occurred_on', 'reported_by_user_id',
])]
class DefaultFlag extends Model
{
    use BelongsToStore;

    /** @use HasFactory<DefaultFlagFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_involved' => 'decimal:2',
            'occurred_on' => 'date',
            'verified_at' => 'datetime',
            'reason' => DefaultFlagReason::class,
            'status' => DefaultFlagStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', DefaultFlagStatus::Verified->value);
    }
}
