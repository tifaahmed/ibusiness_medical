<?php

namespace App\Actions\Contact;

use App\Enums\Contact\ContactLogActionEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Models\ContactMessage;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Applies an admin's edit and records one log row per field that actually
 * moved.
 *
 * A salesperson is logged by NAME so the trail still reads correctly once that
 * salesperson is deleted; a status is logged by VALUE because its name depends
 * on the language the log is being READ in, not the one it was written in.
 */
class UpdateContactMessageAction
{
    /**
     * @param  array{status?: string, sales_id?: int|null, admin_notes?: string|null}  $attributes
     */
    public function handle(ContactMessage $enquiry, array $attributes, ?User $actor = null): ContactMessage
    {
        return DB::transaction(function () use ($enquiry, $attributes, $actor) {
            $logs = [];

            if (array_key_exists('status', $attributes)) {
                $status = ContactStatusEnum::from($attributes['status']);

                if ($status !== $enquiry->status) {
                    $logs[] = [
                        'action' => ContactLogActionEnum::STATUS_CHANGED->value,
                        'old_value' => $enquiry->status->value,
                        'new_value' => $status->value,
                    ];

                    $enquiry->status = $status;

                    /* Stamped the first time it reaches resolved, and left
                       alone after: it records when the enquiry was answered,
                       not the last time somebody touched the dropdown. */
                    if ($status === ContactStatusEnum::RESOLVED && $enquiry->replied_at === null) {
                        $enquiry->replied_at = now();
                    }
                }
            }

            if (array_key_exists('sales_id', $attributes)) {
                $salesId = $attributes['sales_id'];

                if ($salesId !== $enquiry->sales_id) {
                    $logs[] = [
                        'action' => ContactLogActionEnum::SALES_ASSIGNED->value,
                        'old_value' => $enquiry->sales?->name,
                        'new_value' => $salesId === null ? null : Sales::find($salesId)?->name,
                    ];

                    $enquiry->sales_id = $salesId;
                }
            }

            if (array_key_exists('admin_notes', $attributes)) {
                $note = $attributes['admin_notes'];

                if ($note !== $enquiry->admin_notes) {
                    $logs[] = [
                        'action' => ContactLogActionEnum::NOTE_UPDATED->value,
                        'old_value' => $enquiry->admin_notes,
                        'new_value' => $note,
                    ];

                    $enquiry->admin_notes = $note;
                }
            }

            $enquiry->save();

            foreach ($logs as $log) {
                $enquiry->logs()->create([...$log, 'admin_id' => $actor?->id]);
            }

            return $enquiry->refresh();
        });
    }
}
