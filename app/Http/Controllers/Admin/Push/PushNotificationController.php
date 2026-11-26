<?php

namespace App\Http\Controllers\Admin\Push;

use App\Console\Commands\SendScheduledPushNotifications;
use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotificationJob;
use App\Models\DevicePushToken;
use App\Models\Governorate;
use App\Models\PushNotification;
use App\Models\PushSchedule;
use App\Services\Push\FcmClient;
use App\Support\PushAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/** Admin page: write a notification and send it to every phone that has the app (open or closed). Route-gated by `send push notifications`. */
class PushNotificationController extends Controller
{
    public function index(FcmClient $fcm): Response
    {
        return Inertia::render('Admin/Push/Index', [
            'configured' => $fcm->isConfigured(),
            'devices' => [
                'total' => DevicePushToken::query()->count(),
                'android' => DevicePushToken::query()->where('platform', 'android')->count(),
                'ios' => DevicePushToken::query()->where('platform', 'ios')->count(),
                'members' => DevicePushToken::query()->whereNotNull('user_id')->distinct()->count('user_id'),
            ],
            'governorates' => Governorate::query()->orderBy('id')->get(['id', 'name'])->map(fn ($g) => ['id' => $g->id, 'name' => $g->getTranslation('name', app()->getLocale(), true)]),
            'rows' => PushNotification::query()->with('creator:id,name')->latest('id')->limit(30)->get()->map(fn (PushNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'audience' => $n->audience,
                'audience_phone' => $n->audience_phone,
                'audience_filters' => $n->audience_filters,
                'status' => $n->status,
                'total' => $n->total,
                'delivered' => $n->delivered,
                'failed' => $n->failed,
                'removed' => $n->removed,
                'error' => $n->error,
                'by' => $n->creator?->name,
                'created_at' => $n->created_at?->format('Y-m-d H:i'),
                'scheduled' => $n->push_schedule_id !== null,
            ]),
            // False = the server cron has not run the scheduler lately, so nothing scheduled would go out.
            'schedulerRunning' => (int) Cache::get(SendScheduledPushNotifications::HEARTBEAT, 0) >= now()->subMinutes(5)->timestamp,
            'timezone' => config('app.timezone'),
            'schedules' => PushSchedule::query()->with('creator:id,name')->orderByDesc('is_active')->orderBy('next_run_at')->latest('id')->get()->map(fn (PushSchedule $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'body' => $s->body,
                'url' => $s->url,
                'audience' => $s->audience,
                'frequency' => $s->frequency,
                'run_date' => $s->run_date?->format('Y-m-d'),
                'weekday' => $s->weekday,
                'month_day' => $s->month_day,
                'send_time' => $s->send_time,
                'is_active' => $s->is_active,
                'next_run_at' => $s->next_run_at?->format('Y-m-d H:i'),
                'runs_count' => $s->runs_count,
                'last_error' => $s->last_error,
                'by' => $s->creator?->name,
            ]),
        ]);
    }

    /** How many phones a set of group filters reaches right now (shown before sending). */
    public function count(Request $request): JsonResponse
    {
        $q = PushAudience::query($request->input('filters', []));

        return response()->json(['devices' => (clone $q)->count(), 'members' => (clone $q)->whereNotNull('user_id')->distinct()->count('user_id')]);
    }

    public function store(Request $request, FcmClient $fcm): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
            // An https link or an in-app path ("/products"); opened when the member taps the notification.
            'url' => ['nullable', 'string', 'max:500', 'regex:/^(https:\/\/|\/)/'],
            'audience' => ['required', 'in:all,member,group'],
            'filters' => ['nullable', 'array'],
            'audience_phone' => ['required_if:audience,member', 'nullable', 'string', 'max:5000'],
            // When: now (default), or later — once on a date, every week, every month.
            'when' => ['nullable', 'in:now,once,weekly,monthly'],
            'send_time' => ['required_if:when,once,weekly,monthly', 'nullable', 'date_format:H:i'],
            'run_date' => ['required_if:when,once', 'nullable', 'date_format:Y-m-d'],
            'weekday' => ['required_if:when,weekly', 'nullable', 'integer', 'between:0,6'],
            'month_day' => ['required_if:when,monthly', 'nullable', 'integer', 'between:1,31'],
        ]);
        $when = $data['when'] ?? 'now';

        $phones = $data['audience'] === 'member' ? PushNotification::parsePhones($data['audience_phone'] ?? '') : [];
        if ($data['audience'] === 'member' && ($phones === [] || count($phones) > 500)) {
            return back()->withErrors(['audience_phone' => __('admin.push.phones_invalid')]);
        }

        $filters = $data['audience'] === 'group' ? PushAudience::clean($data['filters'] ?? []) : [];
        // A scheduled group is counted when it is sent, so it may be empty today.
        if ($when === 'now' && $data['audience'] === 'group' && PushAudience::query($filters)->count() === 0) {
            return back()->withErrors(['filters' => __('admin.push.group_empty')]);
        }

        if ($when === 'now' && ! $fcm->isConfigured()) {
            return back()->with('error', __('admin.push.not_configured'));
        }

        $message = [
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'] ?? null,
            'audience' => $data['audience'],
            'audience_phone' => $phones === [] ? null : implode(',', $phones),
            'audience_filters' => $filters === [] ? null : $filters,
        ];

        if ($when !== 'now') {
            return $this->schedule($request, $message, $when, $data);
        }

        $note = PushNotification::query()->create($message + [
            'status' => PushNotification::STATUS_SENDING,
            'created_by' => $request->user()->id,
        ]);

        Log::info('Push notification queued by an admin.', ['id' => $note->id, 'admin_id' => $request->user()->id, 'audience' => $note->audience]);

        // Runs right after this response, so the admin is not kept waiting and no queue worker is needed.
        SendPushNotificationJob::dispatch($note->id)->afterResponse();

        return back()->with('success', __('admin.push.started'));
    }

    /** Pause a schedule, or switch it on again from the next time that is still ahead. */
    public function toggleSchedule(Request $request, PushSchedule $schedule): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];

        if (! $active) {
            $schedule->update(['is_active' => false, 'next_run_at' => null]);
        } else {
            $next = $schedule->nextRunAfter(now());
            if ($next === null) {
                return back()->with('error', __('admin.push.schedule_past'));
            }
            $schedule->update(['is_active' => true, 'next_run_at' => $next, 'last_error' => null]);
        }

        Log::info('Push schedule switched by an admin.', ['schedule_id' => $schedule->id, 'admin_id' => $request->user()->id, 'active' => (bool) $active]);

        return back()->with('success', __($active ? 'admin.push.schedule_resumed' : 'admin.push.schedule_paused'));
    }

    /** Soft delete: the row and everything it already sent are kept. */
    public function destroySchedule(Request $request, PushSchedule $schedule): RedirectResponse
    {
        $schedule->update(['is_active' => false, 'next_run_at' => null]);
        $schedule->delete();

        Log::info('Push schedule deleted by an admin.', ['schedule_id' => $schedule->id, 'admin_id' => $request->user()->id]);

        return back()->with('success', __('admin.push.schedule_deleted'));
    }

    /**
     * @param  array<string, mixed>  $message
     * @param  array<string, mixed>  $data
     */
    private function schedule(Request $request, array $message, string $frequency, array $data): RedirectResponse
    {
        $schedule = new PushSchedule($message + [
            'frequency' => $frequency,
            'send_time' => $data['send_time'],
            'run_date' => $frequency === PushSchedule::ONCE ? $data['run_date'] : null,
            'weekday' => $frequency === PushSchedule::WEEKLY ? (int) $data['weekday'] : null,
            'month_day' => $frequency === PushSchedule::MONTHLY ? (int) $data['month_day'] : null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);
        $next = $schedule->nextRunAfter(now());

        if ($next === null) {
            return back()->withErrors(['run_date' => __('admin.push.schedule_past')]);
        }

        $schedule->next_run_at = $next;
        $schedule->save();

        Log::info('Push notification scheduled by an admin.', ['schedule_id' => $schedule->id, 'admin_id' => $request->user()->id, 'frequency' => $frequency, 'next_run_at' => $next->toDateTimeString()]);

        return back()->with('success', __('admin.push.scheduled', ['at' => $next->format('Y-m-d H:i')]));
    }
}
