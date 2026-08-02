<?php

namespace Database\Factories;

use App\Enums\DefaultFlagReason;
use App\Enums\DefaultFlagStatus;
use App\Models\Customer;
use App\Models\DefaultFlag;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DefaultFlag>
 */
class DefaultFlagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'reason' => fake()->randomElement(DefaultFlagReason::cases()),
            'status' => DefaultFlagStatus::Pending,
            'amount_involved' => fake()->numberBetween(10, 200) * 1000,
            'narrative' => fake()->sentence(12),
            'evidence_path' => 'evidence/sample.pdf',
            'occurred_on' => Carbon::instance(fake()->dateTimeBetween('-2 years', '-1 month')),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'status' => DefaultFlagStatus::Verified,
            'verified_at' => Carbon::now(),
        ]);
    }

    public function reason(DefaultFlagReason $reason): static
    {
        return $this->state(fn () => ['reason' => $reason]);
    }
}
