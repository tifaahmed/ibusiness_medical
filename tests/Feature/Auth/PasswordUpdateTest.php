<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/user/profile')->put('/user/password', [
            'current_password' => 'password',
            'password' => 'new-Password-123',
            'password_confirmation' => 'new-Password-123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-Password-123', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/user/profile')->put('/user/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-Password-123',
            'password_confirmation' => 'new-Password-123',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}
