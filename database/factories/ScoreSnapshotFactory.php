<?php

namespace Database\Factories;

use App\Enums\RiskBand;
use App\Models\Customer;
use App\Models\ScoreSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoreSnapshot>
 */
class ScoreSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $score = fake()->numberBetween(300, 900);

        return [
            'customer_id' => Customer::factory(),
            'score' => $score,
            'band' => RiskBand::forScore($score),
            'breakdown' => ['components' => [], 'weight_total' => 0, 'algorithm_version' => '1.0'],
            'recommended_credit_limit' => 0,
            'observation_count' => 0,
            'algorithm_version' => '1.0',
            'computed_at' => now(),
        ];
    }
}
