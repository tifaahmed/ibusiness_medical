<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->unique()->company();

        return [
            'title' => ['en' => $title, 'ar' => $title],
            'description' => null,
            'short_description' => null,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 100000),
            'youtube_link' => null,
            'offer_percent_from' => null,
            'offer_percent_to' => null,
        ];
    }
}
