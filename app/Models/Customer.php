<?php

namespace App\Models;

use App\Support\BlindIndex;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

/**
 * A network-wide identity shared by every store, not a per-store contact row.
 * Identifiers live encrypted at rest with a companion blind index for lookup.
 */
#[Fillable([
    'full_name', 'mobile', 'pan', 'aadhaar_last4', 'date_of_birth',
    'address_line', 'city', 'state', 'pincode', 'created_by_store_id',
])]
#[Hidden(['mobile_hash', 'pan_hash', 'aadhaar_hash'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'address_line' => 'encrypted',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Ciphertext and blind index are written together by the mutator rather
     * than by a saving hook, so the two can never drift apart. A hook would be
     * skipped by mass inserts and by seeders using WithoutModelEvents, leaving
     * a customer who exists but can never be found again.
     */
    protected function mobile(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Crypt::decryptString($value),
            set: fn (?string $value) => [
                'mobile' => $value === null ? null : Crypt::encryptString($value),
                'mobile_hash' => BlindIndex::forMobile($value),
            ],
        );
    }

    protected function pan(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : Crypt::decryptString($value),
            set: fn (?string $value) => [
                'pan' => blank($value) ? null : Crypt::encryptString(strtoupper($value)),
                'pan_hash' => BlindIndex::forPan($value),
            ],
        );
    }

    /**
     * Full Aadhaar numbers are never stored. The caller passes the number once,
     * we keep the keyed hash for lookup and the last four digits for display.
     */
    public function setAadhaar(?string $aadhaar): void
    {
        $digits = preg_replace('/\D/', '', (string) $aadhaar);

        $this->aadhaar_hash = BlindIndex::forAadhaar($digits);
        $this->aadhaar_last4 = strlen($digits) === 12 ? substr($digits, -4) : null;
    }

    public function scopeMatchingIdentifier(Builder $query, string $term): Builder
    {
        $hashes = array_filter([
            BlindIndex::forMobile($term),
            BlindIndex::forPan($term),
            BlindIndex::forAadhaar($term),
        ]);

        if ($hashes === []) {
            // Not a recognisable identifier, so fall back to a name search.
            return $query->where('full_name', 'like', '%'.$term.'%');
        }

        return $query->where(function (Builder $q) use ($hashes) {
            $q->whereIn('mobile_hash', $hashes)
                ->orWhereIn('pan_hash', $hashes)
                ->orWhereIn('aadhaar_hash', $hashes);
        });
    }

    public function stores(): BelongsToMany
    {
        // Explicit table name: Laravel would otherwise infer customer_store.
        return $this->belongsToMany(Store::class, 'store_customer')
            ->withPivot(['local_reference', 'notes', 'first_seen_at'])
            ->withTimestamps();
    }

    public function createdByStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'created_by_store_id');
    }

    public function udhaars(): HasMany
    {
        return $this->hasMany(Udhaar::class);
    }

    public function goldLoans(): HasMany
    {
        return $this->hasMany(GoldLoan::class);
    }

    public function defaultFlags(): HasMany
    {
        return $this->hasMany(DefaultFlag::class);
    }

    public function scoreSnapshots(): HasMany
    {
        return $this->hasMany(ScoreSnapshot::class);
    }

    public function latestScore(): HasOne
    {
        return $this->hasOne(ScoreSnapshot::class)->latestOfMany('computed_at');
    }

    public function maskedMobile(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->mobile);

        return strlen($digits) >= 4 ? str_repeat('X', max(0, strlen($digits) - 4)).substr($digits, -4) : '';
    }

    public function maskedPan(): ?string
    {
        return $this->pan ? substr($this->pan, 0, 3).'XXXX'.substr($this->pan, -3) : null;
    }
}
