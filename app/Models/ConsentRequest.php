<?php

namespace App\Models;

use App\Enums\ConsentStatus;
use App\Models\Concerns\BelongsToStore;
use Database\Factories\ConsentRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'store_id', 'customer_id', 'requested_by_user_id', 'purpose', 'status',
    'otp_hash', 'otp_expires_at', 'ip_address',
])]
#[Hidden(['otp_hash'])]
class ConsentRequest extends Model
{
    use BelongsToStore;

    /** @use HasFactory<ConsentRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ConsentStatus::class,
            'otp_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'grant_expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isOtpExpired(): bool
    {
        return $this->otp_expires_at->isPast();
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempts < (int) config('goldscore.consent.max_attempts');
    }

    public function grantsAccess(): bool
    {
        return $this->status === ConsentStatus::Verified
            && $this->grant_expires_at !== null
            && $this->grant_expires_at->isFuture();
    }

    /**
     * A live consent window for this store and customer. Scoped to the store so
     * one jeweller's authorisation never unlocks the report for another.
     */
    public function scopeActiveGrant(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId)
            ->where('status', ConsentStatus::Verified->value)
            ->where('grant_expires_at', '>', Carbon::now());
    }
}
