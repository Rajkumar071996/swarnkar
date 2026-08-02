<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $cities = [
            ['Jaipur', 'Rajasthan'],
            ['Surat', 'Gujarat'],
            ['Coimbatore', 'Tamil Nadu'],
            ['Hyderabad', 'Telangana'],
            ['Kolkata', 'West Bengal'],
        ];

        [$city, $state] = fake()->randomElement($cities);

        return [
            'full_name' => fake()->name(),
            'mobile' => fake()->unique()->numerify('9#########'),
            'pan' => strtoupper(fake()->unique()->bothify('?????####?')),
            'date_of_birth' => fake()->dateTimeBetween('-65 years', '-20 years'),
            'address_line' => fake()->streetAddress(),
            'city' => $city,
            'state' => $state,
            'pincode' => fake()->numerify('######'),
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn () => ['full_name' => $name]);
    }

    public function withMobile(string $mobile): static
    {
        return $this->state(fn () => ['mobile' => $mobile]);
    }
}
