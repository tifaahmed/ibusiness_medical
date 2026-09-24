<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StoreBranch>
 */
class StoreBranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->streetName();

        return [
            'store_id' => Store::factory(),
            'governorate_id' => null,
            'city_id' => null,
            'latitude' => null,
            'longitude' => null,
            'google_location_url' => null,
            'name' => ['en' => $name, 'ar' => $name],
            'slug' => \Illuminate\Support\Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 100000),
            'address' => ['en' => $this->faker->address(), 'ar' => $this->faker->address()],
            'area' => null,
            'phone' => null,
        ];
    }
}
