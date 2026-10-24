<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** The email a member saves on their profile is read back by GET /profile (the app pre-fills the payment page with it). */
class ProfileEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function a_saved_email_is_returned_by_the_profile(): void
    {
        $user = User::factory()->create(['phone' => '01011112222', 'email' => null]);
        Membership::factory()->create(['user_id' => $user->id, 'national_id' => null]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['name' => 'Mona Hassan', 'email' => 'mona@example.com', 'governorate_id' => null, 'city_id' => null])->assertOk();

        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.user.email', 'mona@example.com');
        $this->assertSame('mona@example.com', $user->fresh()->email);
    }

    /** @test */
    public function the_date_of_birth_is_saved_and_read_back_and_a_future_one_is_refused(): void
    {
        $user = User::factory()->create(['phone' => '01011113333']);
        Membership::factory()->create(['user_id' => $user->id, 'national_id' => null]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['date_of_birth' => '1995-06-15'])->assertOk();
        $this->getJson('/api/v1/profile')->assertOk()->assertJsonPath('data.user.date_of_birth', '1995-06-15');

        $this->putJson('/api/v1/profile', ['date_of_birth' => now()->addDay()->toDateString()])->assertUnprocessable();
        $this->putJson('/api/v1/profile', ['date_of_birth' => null])->assertOk();
        $this->getJson('/api/v1/profile')->assertJsonPath('data.user.date_of_birth', null);
    }
}
