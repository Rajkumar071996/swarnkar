<?php

namespace Database\Factories;

use App\Enums\GoldLoanStatus;
use App\Models\Customer;
use App\Models\GoldLoan;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoldLoan>
 */
class GoldLoanFactory extends Factory
{
    public function definition(): array
    {
        $disbursedOn = Carbon::instance(fake()->dateTimeBetween('-2 years', '-6 months'));

        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'loan_no' => 'GL-'.Str::upper(Str::random(8)),
            'principal_amount' => fake()->numberBetween(20, 300) * 1000,
            'paid_from' => 'cash',
            'interest_rate' => fake()->randomElement([9.5, 11.0, 12.5]),
            'pledged_weight_grams' => fake()->randomFloat(3, 5, 80),
            'purity_karat' => 22,
            'disbursed_on' => $disbursedOn,
            'due_on' => $disbursedOn->copy()->addMonths(6),
            'status' => GoldLoanStatus::Active,
        ];
    }

    public function closedOnTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_on' => Carbon::parse($attributes['due_on'])->subDays(5),
            'status' => GoldLoanStatus::Closed,
        ]);
    }

    public function auctioned(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_on' => Carbon::parse($attributes['due_on'])->addMonths(3),
            'status' => GoldLoanStatus::Auctioned,
        ]);
    }
}
