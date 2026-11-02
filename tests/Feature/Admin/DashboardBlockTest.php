<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureAdminAreaAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/** A blocked account keeps its permissions but is refused at the admin area's door. */
class DashboardBlockTest extends TestCase
{
    use RefreshDatabase;

    private function pass(User $user): bool
    {
        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $user);

        try {
            (new EnsureAdminAreaAccess)->handle($request, fn () => response('ok'));

            return true;
        } catch (AccessDeniedHttpException) {
            return false;
        }
    }

    public function test_blocked_user_is_refused_and_unblocked_user_is_admitted(): void
    {
        Permission::findOrCreate('manage settings', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('manage settings');

        $this->assertTrue($this->pass($user));

        // Not mass assignable on purpose, so a profile form can never block or unblock anyone.
        $user->forceFill(['dashboard_blocked_at' => now()])->save();
        $this->assertFalse($this->pass($user->fresh()));

        $user->forceFill(['dashboard_blocked_at' => null])->save();
        $this->assertTrue($this->pass($user->fresh()));
    }

    private function admin(): User
    {
        Permission::findOrCreate('manage settings', 'web');
        Permission::findOrCreate('manage admin users', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('manage settings');

        return $user;
    }

    public function test_a_blocked_admin_with_a_live_session_is_thrown_out_on_the_next_request(): void
    {
        $user = $this->admin();
        $user->forceFill(['password' => bcrypt('secret-pass-1'), 'email_verified_at' => now()])->save();

        // A real sign-in, so there is a real session to throw out.
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1']);
        $this->get(route('admin.setting.list'))->assertOk();

        $user->forceFill(['dashboard_blocked_at' => now()])->save();
        $this->app['auth']->forgetGuards(); // a test keeps one app alive; a real request would load the user afresh

        $this->get(route('admin.setting.list'))->assertForbidden();
        // The session itself is gone: nothing of the login is left, and the next request gets in nowhere.
        $next = $this->get(route('admin.setting.list'));
        $this->assertNotSame(200, $next->getStatusCode(), 'the blocked admin got back in');
        $this->assertNull(session()->get(auth()->guard('web')->getName()), 'the login is still in the session');
    }

    public function test_blocking_deletes_the_admins_session_rows_and_rotates_the_remember_token(): void
    {
        config(['session.driver' => 'database']);
        \Spatie\Permission\Models\Role::findOrCreate('viewer', 'web');
        $boss = $this->admin();
        $boss->givePermissionTo('manage admin users');
        $target = $this->admin();
        $token = $target->remember_token;
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            ['id' => 'live-session-1', 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()],
            ['id' => 'someone-else', 'user_id' => $boss->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()],
        ]);

        $this->actingAs($boss)->put(route('admin.admin-users.update', $target->id), ['name' => $target->name, 'email' => $target->email, 'roles' => ['viewer'], 'permissions' => ['manage settings'], 'dashboard_blocked' => true])
            ->assertSessionHasNoErrors(); // printed below if it fails

        $this->assertNotNull($target->fresh()->dashboard_blocked_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'live-session-1']);
        $this->assertDatabaseHas('sessions', ['id' => 'someone-else']);
        $this->assertNotSame($token, $target->fresh()->remember_token);
    }

    public function test_a_blocked_admin_is_refused_with_an_api_token_too(): void
    {
        $user = $this->admin();
        $user->forceFill(['dashboard_blocked_at' => now()])->save();

        \Laravel\Sanctum\Sanctum::actingAs($user->fresh());
        $this->getJson(route('admin.setting.list'))->assertForbidden();
    }

    public function test_a_blocked_admin_cannot_sign_in_and_the_message_says_why(): void
    {
        $user = $this->admin();
        $user->forceFill(['dashboard_blocked_at' => now(), 'password' => bcrypt('secret-pass-1')])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass-1'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
