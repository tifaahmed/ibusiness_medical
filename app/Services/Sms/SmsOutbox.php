<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use App\Support\QuickAdd;
use Illuminate\Support\Facades\Log;

/**
 * Every customer message goes through here: it is RECORDED first (with its length), and only handed to the gateway
 * when the admin's sending switch is on and the gateway is configured. With the switch off a message stays `queued`
 * and can be sent later from /admin/sms.
 */
class SmsOutbox
{
    public function __construct(private readonly SmsGateway $gateway) {}

    /** @param array{user_id?: ?int, membership_id?: ?int, created_by?: ?int} $context */
    public function submit(string $phone, string $body, string $purpose, array $context = []): SmsMessage
    {
        $size = QuickAdd::size($body);

        $message = SmsMessage::query()->create($context + [
            'phone' => $phone, 'purpose' => $purpose, 'body' => $body,
            'characters' => $size['characters'], 'segments' => $size['segments'], 'status' => SmsMessage::STATUS_QUEUED,
        ]);

        return $this->dispatch($message);
    }

    /** Sends one queued/failed message if sending is allowed now; otherwise leaves it as it is. */
    public function dispatch(SmsMessage $message): SmsMessage
    {
        if ($message->status === SmsMessage::STATUS_SENT || ! QuickAdd::smsEnabled() || ! SmsGateway::isConfigured()) {
            return $message;
        }

        try {
            $result = $this->gateway->send($message->phone, $message->body);
        } catch (\Throwable $e) {
            Log::error('SMS outbox: gateway threw.', ['sms_message_id' => $message->id, 'error' => $e->getMessage()]);
            $result = ['sent' => false, 'reference' => null, 'error' => 'Gateway error.'];
        }

        $message->forceFill($result['sent']
            ? ['status' => SmsMessage::STATUS_SENT, 'reference' => $result['reference'], 'error' => null, 'sent_at' => now()]
            : ['status' => SmsMessage::STATUS_FAILED, 'error' => $result['error']])->save();

        if (! $result['sent']) {
            Log::warning('SMS outbox: message not sent.', ['sms_message_id' => $message->id, 'error' => $result['error']]);
        }

        return $message;
    }
}
