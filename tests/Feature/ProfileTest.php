<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The Jetstream profile lives at /user/profile and needs the `manage profile` permission. */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('manage profile', 'web'));

        return $user;
    }

    public function test_profile_page_is_displayed_to_staff_with_the_permission(): void
    {
        $this->actingAs($this->staff())->get('/user/profile')->assertOk();
    }

    public function test_profile_page_is_refused_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create())->get('/user/profile')->assertForbidden();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->staff();

        $this->actingAs($user)->from('/user/profile')->put('/user/profile-information', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_an_email_already_used_by_someone_else_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = $this->staff();

        $this->actingAs($user)->from('/user/profile')->put('/user/profile-information', [
            'name' => $user->name,
            'email' => 'taken@example.com',
        ])->assertSessionHasErrorsIn('updateProfileInformation', 'email');
    }
}
