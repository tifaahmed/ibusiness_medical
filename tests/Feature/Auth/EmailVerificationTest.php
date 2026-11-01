<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** E-mail verification is off (`Features::emailVerification()` is commented out in config/fortify.php): there is no verification screen or link. */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_email_verification_screen(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
    }

    public function test_there_is_no_verification_link_route(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('verification.verify'));
    }
}
