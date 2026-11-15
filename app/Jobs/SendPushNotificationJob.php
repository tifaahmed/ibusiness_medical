<?php

namespace App\Jobs;

use App\Models\DevicePushToken;
use App\Models\PushNotification;
use App\Services\Push\FcmClient;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one PushNotification to its devices. Deliberately NOT queued: it is run right after the admin's response
 * (`->afterResponse()`), so it works on a host with no queue worker. Progress and the outcome are written onto the row.
 */
class SendPushNotificationJob
{
    use Dispatchable;

    public function __construct(private int $notificationId) {}

    public function handle(FcmClient $fcm): void
    {
        $note = PushNotification::query()->find($this->notificationId);

        if ($note === null || $note->status !== PushNotification::STATUS_SENDING) {
            return;
        }

        @set_time_limit(0);

        try {
            $tokens = DevicePushToken::query()
                ->when($note->audience === 'member', fn ($q) => $q->whereHas('user', fn ($u) => $u->whereIn('phone', PushNotification::parsePhones($note->audience_phone))))
                ->pluck('token', 'id');

            $note->update(['total' => $tokens->count()]);

            $results = $fcm->sendMany($tokens->values()->all(), $note->title, $note->body, $note->url ? ['url' => $note->url] : []);
            $counts = array_count_values($results);

            $dead = $tokens->filter(fn ($token) => ($results[$token] ?? null) === 'removed')->keys();
            if ($dead->isNotEmpty()) {
                // A token FCM reports as unregistered belongs to an uninstalled app; the row is only a device address.
                DevicePushToken::query()->whereIn('id', $dead)->delete();
            }

            $note->update([
                'status' => PushNotification::STATUS_SENT,
                'delivered' => $counts['ok'] ?? 0,
                'failed' => $counts['failed'] ?? 0,
                'removed' => $counts['removed'] ?? 0,
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Push notification could not be sent.', ['notification_id' => $this->notificationId, 'message' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
            $note->update(['status' => PushNotification::STATUS_FAILED, 'error' => mb_substr($exception->getMessage(), 0, 1000), 'finished_at' => now()]);
        }
    }
}
