<?php

namespace Database\Factories;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Generate a slug from membership number with random suffix for uniqueness.
     */
    private function generateSlugFromMembershipNumber(string $membershipNumber): string
    {
        $baseSlug = Str::slug($membershipNumber);
        $randomSuffix = rand(1000, 9999);
        return $baseSlug . '-' . $randomSuffix;
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registrationDate = Carbon::now()->subMonths(rand(1, 6));
        $expirationDate = $registrationDate->copy()->addMonths(rand(6, 12));
        $membershipNumber = 'MEM-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT) . '-' . Carbon::now()->format('Y') . '-' . str_pad(rand(1, 99), 2, '0', STR_PAD_LEFT);
        
        return [
            'user_id' => User::factory(),
            'membership_number' => $membershipNumber,
            'slug' => $this->generateSlugFromMembershipNumber($membershipNumber),
            'registration_date' => $registrationDate,
            'expiration_date' => $expirationDate,
            'is_active' => false,
        ];
    }

    /**
     * Indicate that the membership is active and not expired.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $registrationDate = Carbon::now()->subMonths(rand(1, 6));
            $expirationDate = Carbon::now()->addMonths(rand(6, 12));
            
            return [
                'registration_date' => $registrationDate,
                'expiration_date' => $expirationDate,
                'is_active' => true,
            ];
        });
    }

    /**
     * Indicate that the membership is expired.
     */
    public function expired(): static
    {
        return $this->state(function (array $attributes) {
            $registrationDate = Carbon::now()->subYears(rand(2, 5));
            $expirationDate = Carbon::now()->subMonths(rand(1, 12));
            
            return [
                'registration_date' => $registrationDate,
                'expiration_date' => $expirationDate,
                'is_active' => false,
            ];
        });
    }

    /**
     * Indicate that the membership is inactive but not expired.
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            $registrationDate = Carbon::now()->subMonths(rand(1, 6));
            $expirationDate = Carbon::now()->addMonths(rand(1, 6));
            
            return [
                'registration_date' => $registrationDate,
                'expiration_date' => $expirationDate,
                'is_active' => false,
            ];
        });
    }

    /**
     * Set a custom membership number.
     */
    public function withMembershipNumber(string $number): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_number' => $number,
            'slug' => $this->generateSlugFromMembershipNumber($number),
        ]);
    }
}

