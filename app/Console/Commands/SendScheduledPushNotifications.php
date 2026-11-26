<?php

namespace App\Console\Commands;

use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;
use App\Models\PushSchedule;
use App\Services\Push\FcmClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Runs every minute from the scheduler and sends the scheduled notifications whose time has come. */
class SendScheduledPushNotifications extends Command
{
    /** Cache key holding the last time this ran; the admin page warns when it is stale (the server cron is not set up). */
    public const HEARTBEAT = 'push.scheduler_last_run';

    /** A send later than this (server was down) is skipped, so members do not get an old message at a strange hour. */
    public const MAX_LATE_HOURS = 6;

    protected $signature = 'push:send-scheduled {--dry : Only list what is due}';

    protected $description = 'Send the scheduled push notifications that are due';

    public function handle(FcmClient $fcm): int
    {
        $due = PushSchedule::query()->where('is_active', true)->where('next_run_at', '<=', now())->orderBy('next_run_at')->pluck('id');

        if ($this->option('dry')) {
            $this->info('Due schedules: '.($due->implode(', ') ?: 'none'));

            return self::SUCCESS;
        }

        Cache::forever(self::HEARTBEAT, now()->timestamp);

        foreach ($due as $id) {
            try {
                $note = $this->claim($id);

                if ($note === null) {
                    continue;
                }

                if (! $fcm->isConfigured()) {
                    $note->update(['status' => PushNotification::STATUS_FAILED, 'error' => 'Firebase is not set up.', 'finished_at' => now()]);
                    Log::error('Scheduled push not sent: Firebase is not set up.', ['schedule_id' => $id, 'notification_id' => $note->id]);

                    continue;
                }

                SendPushNotificationJob::dispatchSync($note->id);
            } catch (Throwable $exception) {
                Log::error('Scheduled push failed.', ['schedule_id' => $id, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Moves the schedule on to its next time and writes the notification row in ONE locked transaction, so one due
     * time can never be sent twice. Null = nothing to send (no longer due, or too late).
     */
    private function claim(int $id): ?PushNotification
    {
        return DB::transaction(function () use ($id) {
            $schedule = PushSchedule::query()->lockForUpdate()->find($id);

            if ($schedule === null || ! $schedule->is_active || $schedule->next_run_at === null || $schedule->next_run_at->isFuture()) {
                return null;
            }

            $dueAt = $schedule->next_run_at;
            $late = $dueAt->lt(now()->subHours(self::MAX_LATE_HOURS));
            $next = $schedule->nextRunAfter(now());

            $schedule->update([
                'next_run_at' => $next,
                'is_active' => $next !== null,
                'last_run_at' => $late ? $schedule->last_run_at : now(),
                'runs_count' => $schedule->runs_count + ($late ? 0 : 1),
                'last_error' => $late ? 'Missed '.$dueAt->format('Y-m-d H:i').': the server scheduler was not running.' : null,
            ]);

            if ($late) {
                Log::error('Scheduled push skipped because it was too late.', ['schedule_id' => $id, 'due_at' => $dueAt->toDateTimeString()]);

                return null;
            }

            return PushNotification::query()->create([
                'push_schedule_id' => $schedule->id,
                'title' => $schedule->title,
                'body' => $schedule->body,
                'url' => $schedule->url,
                'audience' => $schedule->audience,
                'audience_phone' => $schedule->audience_phone,
                'audience_filters' => $schedule->audience_filters,
                'status' => PushNotification::STATUS_SENDING,
                'created_by' => $schedule->created_by,
            ]);
        });
    }
}
