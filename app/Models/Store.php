<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'legal_name', 'gstin', 'phone', 'email',
        'address_line', 'city', 'state', 'pincode', 'is_active',
        'opening_capital', 'cash_in_hand', 'bank_balance',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_capital' => 'decimal:2',
            'cash_in_hand' => 'decimal:2',
            'bank_balance' => 'decimal:2',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(StoreExpense::class);
    }

    /**
     * How this store is described in a cross-store report. The brand is masked
     * so a lookup cannot be used to map a competitor's customer book; the
     * street address is shown separately for location.
     */
    public function anonymisedLabel(): string
    {
        return 'XXXXX jeweller';
    }

    public function fullAddress(): ?string
    {
        $parts = array_values(array_filter([
            $this->address_line,
            $this->city,
            $this->state,
            $this->pincode,
        ], fn (?string $part) => filled($part)));

        return $parts === [] ? null : implode(', ', $parts);
    }
}
