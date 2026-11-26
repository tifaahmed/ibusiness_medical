<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\SendScheduledPushNotifications;
use App\Models\PushNotification;
use App\Models\PushSchedule;
use App\Models\User;
use App\Services\Push\FcmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Scheduled push notifications: when they are next due, who may set them, and that each due time is sent once. */
class PushScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        Permission::findOrCreate('send push notifications', 'web');
        $admin->givePermissionTo('send push notifications');

        return $admin;
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse($time, config('app.timezone'));
    }

    private function next(array $attributes, string $after): ?string
    {
        return (new PushSchedule($attributes + ['send_time' => '10:00']))->nextRunAfter($this->at($after))?->format('Y-m-d H:i');
    }

    private function fakeFirebase(bool $configured = true): void
    {
        $fcm = $this->mock(FcmClient::class);
        $fcm->shouldReceive('isConfigured')->andReturn($configured);
        $fcm->shouldReceive('sendMany')->andReturn([]);
    }

    /** @test */
    public function it_finds_the_next_time_for_each_kind(): void
    {
        // 2026-11-26 is a Thursday.
        $once = ['frequency' => 'once', 'run_date' => '2026-11-27'];
        $this->assertSame('2026-11-27 10:00', $this->next($once, '2026-11-26 12:00'));
        $this->assertNull($this->next($once, '2026-11-27 10:00'));

        $thursday = ['frequency' => 'weekly', 'weekday' => 4];
        $this->assertSame('2026-11-26 10:00', $this->next($thursday, '2026-11-26 09:00'));
        $this->assertSame('2026-12-03 10:00', $this->next($thursday, '2026-11-26 10:00'));

        $last = ['frequency' => 'monthly', 'month_day' => 31];
        $this->assertSame('2026-11-30 10:00', $this->next($last, '2026-11-26 12:00'));
        $this->assertSame('2026-12-31 10:00', $this->next($last, '2026-11-30 10:00'));
        $this->assertSame('2027-02-28 10:00', $this->next($last, '2027-01-31 11:00'));
    }

    /** @test */
    public function an_admin_schedules_pauses_resumes_and_deletes_without_sending_anything(): void
    {
        $this->travelTo($this->at('2026-11-26 12:00'));
        $this->fakeFirebase();
        $this->actingAs($this->admin());
        $form = ['title' => 'Hi', 'body' => 'Body', 'audience' => 'all', 'when' => 'weekly', 'weekday' => 6, 'send_time' => '18:30'];

        $this->post(route('admin.push.store'), $form)->assertSessionHasNoErrors();
        $schedule = PushSchedule::query()->sole();
        $this->assertSame('2026-11-28 18:30', $schedule->next_run_at->format('Y-m-d H:i'));
        $this->assertSame(0, PushNotification::query()->count());

        $this->patch(route('admin.push.schedules.toggle', $schedule), ['active' => false]);
        $this->assertNull($schedule->refresh()->next_run_at);
        $this->patch(route('admin.push.schedules.toggle', $schedule), ['active' => true]);
        $this->assertTrue($schedule->refresh()->is_active);

        $this->delete(route('admin.push.schedules.destroy', $schedule));
        $this->assertSoftDeleted($schedule);
    }

    /** @test */
    public function a_past_date_and_an_admin_without_the_permission_are_refused(): void
    {
        $this->travelTo($this->at('2026-11-26 12:00'));
        $this->fakeFirebase();
        $form = ['title' => 'Hi', 'body' => 'Body', 'audience' => 'all', 'when' => 'once', 'run_date' => '2026-11-26', 'send_time' => '11:00'];

        $this->actingAs(User::factory()->create())->post(route('admin.push.store'), $form)->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.push.store'), $form)->assertSessionHasErrors('run_date');
        $this->assertSame(0, PushSchedule::query()->count());
    }

    /** @test */
    public function a_due_schedule_is_sent_once_and_moves_to_its_next_time(): void
    {
        $this->travelTo($this->at('2026-11-26 10:00:30'));
        $this->fakeFirebase();
        $weekly = PushSchedule::query()->create(['title' => 'W', 'body' => 'B', 'frequency' => 'weekly', 'weekday' => 4, 'send_time' => '10:00', 'next_run_at' => $this->at('2026-11-26 10:00')]);
        $once = PushSchedule::query()->create(['title' => 'O', 'body' => 'B', 'frequency' => 'once', 'run_date' => '2026-11-26', 'send_time' => '10:00', 'next_run_at' => $this->at('2026-11-26 10:00')]);

        $this->artisan('push:send-scheduled')->assertSuccessful();
        $this->artisan('push:send-scheduled')->assertSuccessful();

        $this->assertSame(2, PushNotification::query()->where('status', 'sent')->count());
        $this->assertSame('2026-12-03 10:00', $weekly->refresh()->next_run_at->format('Y-m-d H:i'));
        $this->assertFalse($once->refresh()->is_active);
        $this->assertSame(1, $once->runs_count);
        $this->assertNotNull(Cache::get(SendScheduledPushNotifications::HEARTBEAT));
    }

    /** @test */
    public function a_time_missed_by_hours_is_skipped_and_firebase_not_set_up_is_recorded(): void
    {
        $this->travelTo($this->at('2026-11-26 20:00'));
        $this->fakeFirebase(false);
        $late = PushSchedule::query()->create(['title' => 'L', 'body' => 'B', 'frequency' => 'once', 'run_date' => '2026-11-26', 'send_time' => '10:00', 'next_run_at' => $this->at('2026-11-26 10:00')]);
        PushSchedule::query()->create(['title' => 'N', 'body' => 'B', 'frequency' => 'once', 'run_date' => '2026-11-26', 'send_time' => '19:59', 'next_run_at' => $this->at('2026-11-26 19:59')]);

        $this->artisan('push:send-scheduled')->assertSuccessful();

        $this->assertStringContainsString('Missed', (string) $late->refresh()->last_error);
        $this->assertSame(['N'], PushNotification::query()->pluck('title')->all());
        $this->assertSame('failed', PushNotification::query()->sole()->status);
    }
}
