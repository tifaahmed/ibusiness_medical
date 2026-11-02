<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\RedirectBackWhenStaying;
use Illuminate\Support\Facades\Route;
use Illuminate\Session\Middleware\StartSession;
use Tests\TestCase;

class SaveAndStayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware([StartSession::class, RedirectBackWhenStaying::class])->group(function () {
            Route::put('/_t/thing/1', fn () => redirect('/_t/thing'));
            Route::put('/_t/bad/1', fn () => back()->withErrors(['x' => 'bad']));
        });
    }

    public function test_stay_header_returns_to_the_edit_page(): void
    {
        $edit = 'http://127.0.0.1:8004/_t/thing/1/edit';
        $this->withHeaders(['X-Save-Stay' => '1', 'referer' => $edit])
            ->put('/_t/thing/1')->assertRedirect($edit);
    }

    public function test_without_header_goes_to_the_list(): void
    {
        $this->withHeaders(['referer' => 'http://127.0.0.1:8004/_t/thing/1/edit'])
            ->put('/_t/thing/1')->assertRedirect('/_t/thing');
    }

    public function test_never_bounces_to_a_non_edit_page_or_other_host(): void
    {
        $this->withHeaders(['X-Save-Stay' => '1', 'referer' => 'http://127.0.0.1:8004/_t/thing/create'])
            ->put('/_t/thing/1')->assertRedirect('/_t/thing');
        $this->withHeaders(['X-Save-Stay' => '1', 'referer' => 'http://evil.test/x/edit'])
            ->put('/_t/thing/1')->assertRedirect('/_t/thing');
    }
}
