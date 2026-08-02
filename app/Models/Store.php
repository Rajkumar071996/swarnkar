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
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * How this store is described in a cross-store report. Trading names are
     * withheld so a lookup cannot be used to map a competitor's customer book.
     */
    public function anonymisedLabel(): string
    {
        return trim(sprintf('A jeweller in %s, %s', $this->city, $this->state), ' ,');
    }
}
