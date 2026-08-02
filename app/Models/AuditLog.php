<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

#[Fillable(['store_id', 'user_id', 'action', 'subject_type', 'subject_id', 'context', 'ip_address'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return ['context' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $subject = null, array $context = []): self
    {
        $user = Auth::user();

        return static::create([
            'store_id' => $user?->store_id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'context' => $context ?: null,
            'ip_address' => Request::ip(),
        ]);
    }
}
