<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
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
            'name' => fake()->lastName().' Jewellers',
            'legal_name' => fake()->lastName().' Jewellers Pvt Ltd',
            'gstin' => strtoupper(fake()->bothify('##???????????#?#')),
            'phone' => fake()->numerify('9#########'),
            'email' => fake()->unique()->companyEmail(),
            'address_line' => fake()->streetAddress(),
            'city' => $city,
            'state' => $state,
            'pincode' => fake()->numerify('######'),
            'is_active' => true,
        ];
    }
}
