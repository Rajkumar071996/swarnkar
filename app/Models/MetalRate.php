<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_id', 'metal_type', 'rate_per_gram', 'effective_on', 'updated_by_user_id'])]
class MetalRate extends Model
{
    use BelongsToStore;

    protected function casts(): array
    {
        return [
            'rate_per_gram' => 'decimal:2',
            'effective_on' => 'date',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function metalLabel(): string
    {
        return config('girvi.metal_types')[$this->metal_type] ?? ucfirst($this->metal_type);
    }
}
