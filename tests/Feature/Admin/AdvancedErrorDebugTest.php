<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AttachErrorDebug;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** A caught-and-logged failure on an admin save must reach the page as flash.error_debug, whichever controller it came from. */
class AdvancedErrorDebugTest extends TestCase
{
    use RefreshDatabase;

    private function run404(User $admin, \Closure $controller): Request
    {
        $request = Request::create('/admin/anything', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $admin);

        (new AttachErrorDebug)->handle($request, $controller);

        return $request;
    }

    public function test_a_caught_exception_is_flashed_with_its_details(): void
    {
        $admin = User::factory()->create();

        $request = $this->run404($admin, function () {
            try {
                throw new \RuntimeException('Column foo cannot be null');
            } catch (\Exception $e) {
                Log::error('Failed to probe', ['error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);

                return redirect('/admin/x')->withErrors(['error' => 'Failed to probe. Please try again.']);
            }
        });

        $d = $request->session()->get('error_debug');
        $this->assertNotNull($d);
        $this->assertStringContainsString('Column foo cannot be null', $d['message']);
        $this->assertSame('POST admin/anything', $d['request']);
        $this->assertSame($admin->id, $d['user_id']);
    }

    public function test_a_plain_validation_error_is_not_a_crash(): void
    {
        $admin = User::factory()->create();
        $this->app['session.store']->forget('error_debug');

        $request = $this->run404($admin, fn () => redirect('/admin/x')->withErrors(['name' => 'bad']));

        $this->assertNull($request->session()->get('error_debug'));
    }
}
