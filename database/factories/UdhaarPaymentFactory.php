<?php

namespace Database\Factories;

use App\Models\Udhaar;
use App\Models\UdhaarPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UdhaarPayment>
 */
class UdhaarPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'udhaar_id' => Udhaar::factory(),
            'amount' => fake()->numberBetween(10, 100) * 500,
            'paid_on' => fake()->dateTimeBetween('-1 year', 'now'),
            'method' => fake()->randomElement(['cash', 'upi', 'card', 'bank_transfer']),
        ];
    }
}
