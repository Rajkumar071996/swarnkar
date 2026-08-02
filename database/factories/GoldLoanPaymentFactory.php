<?php

namespace Database\Factories;

use App\Models\GoldLoan;
use App\Models\GoldLoanPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldLoanPayment>
 */
class GoldLoanPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gold_loan_id' => GoldLoan::factory(),
            'amount' => fake()->numberBetween(5, 100) * 1000,
            'paid_on' => fake()->dateTimeBetween('-1 year', 'now'),
            'method' => 'cash',
        ];
    }
}
