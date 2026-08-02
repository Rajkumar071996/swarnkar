<?php

namespace App\Models\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('store_id') === null) {
                $model->setAttribute('store_id', Auth::user()?->store_id);
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Reads across every store on the network. Only the scoring engine and
     * consent-gated lookups should use this.
     */
    public function scopeNetworkWide(Builder $query): Builder
    {
        return $query->withoutGlobalScope(StoreScope::class);
    }
}
