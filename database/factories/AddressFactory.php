<?php

namespace Database\Factories;

use App\Enums\Address\AddressTypeEnum;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => Membership::factory(),
            'type' => AddressTypeEnum::HOME,
            'address' => $this->faker->address(),
            'street' => $this->faker->streetName(),
            'building_number' => (string) $this->faker->buildingNumber(),
            'apartment_number' => (string) $this->faker->numberBetween(1, 30),
            'floor_number' => (string) $this->faker->numberBetween(1, 15),
            'special_mark' => $this->faker->optional()->word(),
        ];
    }
}
