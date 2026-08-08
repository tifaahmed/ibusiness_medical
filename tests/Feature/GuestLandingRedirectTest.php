<?php

namespace Tests\Feature;

use App\Enums\User\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuestLandingRedirectTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function guests_are_sent_from_the_root_url_to_the_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    /** @test */
    public function the_old_public_pages_funnel_guests_to_the_login_page(): void
    {
        foreach (['/about', '/contact-us', '/partners', '/partners/some-clinic'] as $url) {
            $this->get($url)->assertRedirect('/');
        }

        $this->followingRedirects()->get('/about')->assertSee('login', false);
    }

    /** @test */
    public function admins_landing_on_the_root_url_reach_the_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::ADMIN, 'web'));

        $this->actingAs($admin)->get('/')->assertRedirect(route('admin.dashboard'));
    }

    /**
     * The `guest` middleware bounces authenticated visitors from /login back to
     * the `home` route, so "/" must never answer an authenticated request with
     * another redirect to /login.
     *
     * @test
     */
    public function authenticated_users_do_not_bounce_between_the_root_url_and_login(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findOrCreate(UserRoleEnum::ADMIN, 'web'));

        $this->actingAs($admin)->get(route('login'))->assertRedirect('/');
    }

    /** @test */
    public function a_user_with_nowhere_to_land_is_signed_out_at_the_login_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
