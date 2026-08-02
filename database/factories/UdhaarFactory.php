<?php

namespace Database\Factories;

use App\Enums\UdhaarStatus;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Udhaar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Udhaar>
 */
class UdhaarFactory extends Factory
{
    public function definition(): array
    {
        $issuedOn = Carbon::instance(fake()->dateTimeBetween('-2 years', '-1 month'));

        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'invoice_no' => strtoupper(fake()->bothify('INV-####')),
            'item_description' => fake()->randomElement([
                '22K gold chain', 'Diamond pendant set', 'Bridal bangles pair',
                'Silver pooja set', '18K gold ring',
            ]),
            'principal_amount' => fake()->numberBetween(10, 400) * 500,
            'amount_paid' => 0,
            'issued_on' => $issuedOn,
            'due_on' => $issuedOn->copy()->addDays(30),
            'status' => UdhaarStatus::Open,
        ];
    }

    /**
     * Sets the term explicitly. Chain this before the settlement states, since
     * they derive their dates from whatever the due date is at that point and
     * create() overrides are applied too late to be seen.
     */
    public function issuedOn(Carbon $issuedOn, int $termDays = 30): static
    {
        return $this->state(fn () => [
            'issued_on' => $issuedOn->copy(),
            'due_on' => $issuedOn->copy()->addDays($termDays),
        ]);
    }

    /**
     * Cleared by the due date: the strongest positive udhaar signal.
     */
    public function settledOnTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid' => $attributes['principal_amount'],
            'settled_on' => Carbon::parse($attributes['due_on'])->subDays(fake()->numberBetween(1, 10)),
            'status' => UdhaarStatus::Settled,
        ]);
    }

    public function settledLate(int $daysLate = 45): static
    {
        return $this->state(fn (array $attributes) => [
            'amount_paid' => $attributes['principal_amount'],
            'settled_on' => Carbon::parse($attributes['due_on'])->addDays($daysLate),
            'status' => UdhaarStatus::Settled,
        ]);
    }

    public function overdue(int $daysOverdue = 90): static
    {
        return $this->state(fn () => [
            'issued_on' => Carbon::today()->subDays($daysOverdue + 30),
            'due_on' => Carbon::today()->subDays($daysOverdue),
            'amount_paid' => 0,
            'status' => UdhaarStatus::Defaulted,
        ]);
    }

    public function writtenOff(): static
    {
        return $this->state(fn () => ['status' => UdhaarStatus::WrittenOff]);
    }
}
