<?php

namespace App\Http\Resources;

use App\Models\ScoreSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScoreSnapshot
 */
class ScoreSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band->value,
            'band_label' => $this->band->label(),
            'risk_label' => $this->band->riskLabel(),
            'recommendation' => $this->band->recommendation(),
            'recommended_credit_limit' => (float) $this->recommended_credit_limit,
            'observation_count' => $this->observation_count,
            'algorithm_version' => $this->algorithm_version,
            'computed_at' => $this->computed_at->toIso8601String(),
            'components' => collect($this->breakdown['components'] ?? [])->map(fn (array $c) => [
                'key' => $c['key'],
                'label' => $c['label'],
                'ratio' => $c['ratio'],
                'nominal_weight' => $c['weight'],
                'effective_weight' => $c['effective_weight'] ?? 0.0,
                'has_data' => $c['ratio'] !== null,
                'observations' => $c['observations'],
                'detail' => $c['detail'],
            ])->values(),
        ];
    }
}
