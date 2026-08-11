<?php

namespace Database\Factories;

use App\Enums\FamilyMember\RelationshipEnum;
use App\Models\FamilyMember;
use App\Models\Membership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    protected $model = FamilyMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => Membership::factory(),
            'name' => $this->faker->name(),
            'relationship' => $this->faker->randomElement(RelationshipEnum::cases()),
            'date_of_birth' => $this->faker->dateTimeBetween('-40 years', '-1 year'),
            'phone' => $this->faker->numerify('01#########'),
            'email' => $this->faker->safeEmail(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the family member has been taken off the card.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
