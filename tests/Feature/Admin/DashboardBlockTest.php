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
}
