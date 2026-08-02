<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Confines ledger reads to the signed-in user's own store. Scoring and network
 * lookups deliberately step around this via the networkWide() scope, which is
 * the only sanctioned way to see another store's rows.
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = Auth::user()?->store_id;

        if ($storeId !== null) {
            $builder->where($model->getTable().'.store_id', $storeId);
        }
    }
}
