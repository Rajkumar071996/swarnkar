<?php

namespace App\Services\Scoring;

use App\Enums\RiskBand;
use Illuminate\Support\Carbon;

final class ScoreResult
{
    /**
     * @param  array<int, ScoreComponent>  $components
     */
    public function __construct(
        public readonly ?int $score,
        public readonly RiskBand $band,
        public readonly array $components,
        public readonly float $recommendedCreditLimit,
        public readonly int $observationCount,
        public readonly Carbon $computedAt,
        public readonly string $algorithmVersion = '1.0',
    ) {}

    public function isScored(): bool
    {
        return $this->score !== null;
    }

    /**
     * @return array<int, ScoreComponent>
     */
    public function scoredComponents(): array
    {
        return array_values(array_filter($this->components, fn (ScoreComponent $c) => $c->hasData()));
    }

    public function breakdown(): array
    {
        $scored = $this->scoredComponents();
        $weightTotal = array_sum(array_map(fn (ScoreComponent $c) => $c->weight, $scored));

        return [
            'components' => array_map(fn (ScoreComponent $c) => [
                ...$c->toArray(),
                // What this component was actually worth once the missing ones
                // were dropped and the remaining weights renormalised.
                'effective_weight' => $c->hasData() && $weightTotal > 0
                    ? round($c->weight / $weightTotal * 100, 1)
                    : 0.0,
            ], $this->components),
            'weight_total' => $weightTotal,
            'algorithm_version' => $this->algorithmVersion,
        ];
    }

    public function toSnapshotAttributes(int $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'score' => $this->score,
            'band' => $this->band,
            'breakdown' => $this->breakdown(),
            'recommended_credit_limit' => $this->recommendedCreditLimit,
            'observation_count' => $this->observationCount,
            'algorithm_version' => $this->algorithmVersion,
            'computed_at' => $this->computedAt,
        ];
    }
}
