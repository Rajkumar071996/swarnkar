<?php

namespace App\Services\Scoring;

/**
 * One weighted input to the score. A null ratio means "no evidence either way",
 * which is different from a ratio of 0.0 meaning "evidence of bad behaviour".
 * The engine drops null components and renormalises the remaining weights.
 */
final class ScoreComponent
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?float $ratio,
        public readonly float $weight,
        public readonly int $observations = 0,
        public readonly array $detail = [],
    ) {}

    public static function noData(string $key, string $label, float $weight, array $detail = []): self
    {
        return new self($key, $label, null, $weight, 0, $detail);
    }

    public function hasData(): bool
    {
        return $this->ratio !== null;
    }

    public function points(): ?float
    {
        return $this->ratio === null ? null : $this->ratio * $this->weight;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'ratio' => $this->ratio === null ? null : round($this->ratio, 4),
            'weight' => $this->weight,
            'observations' => $this->observations,
            'detail' => $this->detail,
        ];
    }
}
