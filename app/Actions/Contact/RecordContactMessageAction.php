<?php

namespace App\Actions\Contact;

use App\Enums\Contact\ContactLogActionEnum;
use App\Enums\Contact\ContactSourceEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Services\Sms\SmsGateway;
use App\Support\ContactAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The one way an enquiry gets written, whichever form it came through.
 *
 * Shared by this site's own public endpoint and by the key-gated partner
 * endpoint the Deilar storefront posts to, so an enquiry can never exist
 * without the opening entry in its log — the two are written in the same
 * transaction for exactly that reason.
 */
class RecordContactMessageAction
{
    /**
     * @param  array{phone: string, message: string, name?: ?string, email?: ?string, subject?: ?string, commercial_register?: ?string, source?: ?string, channel?: ?string, ip_address?: ?string, user_agent?: ?string, locale?: ?string, referrer?: ?string, created_at?: ?string}  $attributes
     */
    public function handle(array $attributes): ContactMessage
    {
        $enquiry = DB::transaction(function () use ($attributes) {
            $enquiry = new ContactMessage([
                'phone' => $attributes['phone'],
                'message' => $attributes['message'],
                'name' => $attributes['name'] ?? null,
                'email' => $attributes['email'] ?? null,
                'subject' => $attributes['subject'] ?? null,
                'commercial_register' => $attributes['commercial_register'] ?? null,
                'source' => $attributes['source'] ?? ContactSourceEnum::CONTACT_FORM->value,
                'channel' => $attributes['channel'] ?? null,
                'status' => ContactStatusEnum::NEW->value,
                'ip_address' => $attributes['ip_address'] ?? null,
                'user_agent' => $attributes['user_agent'] ?? null,
                'locale' => $attributes['locale'] ?? null,
                'referrer' => $attributes['referrer'] ?? null,
            ]);

            /*
             * Only the storefront's backfill sends a date, to keep an enquiry's
             * real age as it is copied across; a form never does. Set on the
             * instance rather than mass-assigned, because the timestamps are
             * deliberately not fillable — and Eloquent leaves a timestamp alone
             * once it is dirty, which is exactly the behaviour wanted here.
             */
            if (isset($attributes['created_at'])) {
                $enquiry->created_at = $attributes['created_at'];
                $enquiry->updated_at = $attributes['created_at'];
            }

            $enquiry->save();

            /* The status as its VALUE, never its label: the log is read later,
               possibly in the other language. */
            $log = $enquiry->logs()->make([
                'action' => ContactLogActionEnum::RECEIVED->value,
                'new_value' => $enquiry->status->value,
            ]);

            /* Dated with the enquiry, not with the import that carried it. */
            $log->created_at = $enquiry->created_at;
            $log->updated_at = $enquiry->created_at;
            $log->save();

            return $enquiry;
        });

        $this->notifyInbox($enquiry);
        $this->alertStaffPhone($enquiry);

        return $enquiry;
    }

    /**
     * SMS the staff phone (setting `contact_alert_phone`) when the number that
     * wrote in belongs to nobody in the system AND has never written before, so it can be rung back fast.
     *
     * Like the mail, a failure here is logged and swallowed: the enquiry is
     * already committed and must not turn into a 500 that the storefront
     * re-sends.
     */
    private function alertStaffPhone(ContactMessage $enquiry): void
    {
        try {
            $alertPhone = ContactAlert::phone();

            if ($alertPhone === null
                || ContactAlert::isKnownPhone((string) $enquiry->phone)
                || ! ContactAlert::isFirstEnquiry((string) $enquiry->phone, $enquiry->id)) {
                return;
            }

            $text = sprintf(
                'New %s from %s%s. Call back: %s',
                $enquiry->source->label(),
                $enquiry->name ?: 'a visitor',
                $enquiry->commercial_register ? ' (CR '.$enquiry->commercial_register.')' : '',
                $enquiry->phone,
            );

            $result = app(SmsGateway::class)->send($alertPhone, $text);

            if (! $result['sent']) {
                Log::warning('Contact alert SMS was not sent.', [
                    'contact_message_id' => $enquiry->id,
                    'error' => $result['error'],
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Contact alert SMS failed.', [
                'contact_message_id' => $enquiry->id,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Tell the inbox, without letting a mail problem lose the enquiry.
     *
     * The row is already committed by the time this runs. A mail server that
     * is refusing connections must not turn a captured enquiry into a 500 that
     * makes the storefront queue and re-send one that is already here.
     */
    private function notifyInbox(ContactMessage $enquiry): void
    {
        $inbox = config('mail.from.address');

        if (! is_string($inbox) || $inbox === '') {
            return;
        }

        try {
            Mail::to($inbox)->send(new ContactMessageReceived(
                senderPhone: (string) $enquiry->phone,
                body: (string) $enquiry->message,
                commercialRegister: $enquiry->commercial_register,
                senderName: $enquiry->name,
                source: $enquiry->source->label(),
            ));
        } catch (Throwable $exception) {
            Log::error('Contact enquiry mail failed.', [
                'contact_message_id' => $enquiry->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
