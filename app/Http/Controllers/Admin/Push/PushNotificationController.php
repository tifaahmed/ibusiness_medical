<?php

namespace App\Http\Controllers\Admin\Push;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushNotificationJob;
use App\Models\DevicePushToken;
use App\Models\PushNotification;
use App\Services\Push\FcmClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'rows' => PushNotification::query()->with('creator:id,name')->latest('id')->limit(30)->get()->map(fn (PushNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'audience' => $n->audience,
                'audience_phone' => $n->audience_phone,
                'status' => $n->status,
                'total' => $n->total,
                'delivered' => $n->delivered,
                'failed' => $n->failed,
                'removed' => $n->removed,
                'error' => $n->error,
                'by' => $n->creator?->name,
                'created_at' => $n->created_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function store(Request $request, FcmClient $fcm): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:1000'],
            // An https link or an in-app path ("/products"); opened when the member taps the notification.
            'url' => ['nullable', 'string', 'max:500', 'regex:/^(https:\/\/|\/)/'],
            'audience' => ['required', 'in:all,member'],
            'audience_phone' => ['required_if:audience,member', 'nullable', 'string', 'max:5000'],
        ]);

        $phones = $data['audience'] === 'member' ? PushNotification::parsePhones($data['audience_phone'] ?? '') : [];
        if ($data['audience'] === 'member' && ($phones === [] || count($phones) > 500)) {
            return back()->withErrors(['audience_phone' => __('admin.push.phones_invalid')]);
        }

        if (! $fcm->isConfigured()) {
            return back()->with('error', __('admin.push.not_configured'));
        }

        $note = PushNotification::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'url' => $data['url'] ?? null,
            'audience' => $data['audience'],
            'audience_phone' => $phones === [] ? null : implode(',', $phones),
            'status' => PushNotification::STATUS_SENDING,
            'created_by' => $request->user()->id,
        ]);

        Log::info('Push notification queued by an admin.', ['id' => $note->id, 'admin_id' => $request->user()->id, 'audience' => $note->audience]);

        // Runs right after this response, so the admin is not kept waiting and no queue worker is needed.
        SendPushNotificationJob::dispatch($note->id)->afterResponse();

        return back()->with('success', __('admin.push.started'));
    }
}
