<?php

namespace App\Http\Resources;

use App\Enums\Contact\ContactLogActionEnum;
use App\Enums\Contact\ContactStatusEnum;
use App\Models\ContactMessageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ContactMessage
 */
class ContactMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'commercial_register' => $this->commercial_register,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'sales_id' => $this->sales_id,
            'sales_name' => $this->whenLoaded('sales', fn () => $this->sales?->name),

            /*
             * The visitor's own details, and the note sales keep on them: for
             * an admin, not for whoever the enquiry is about.
             */
            'admin_notes' => $this->when($request->user()?->hasRole('admin'), $this->admin_notes),
            'ip_address' => $this->when($request->user()?->hasRole('admin'), $this->ip_address),
            'user_agent' => $this->when($request->user()?->hasRole('admin'), $this->user_agent),
            'locale' => $this->when($request->user()?->hasRole('admin'), $this->locale),
            'referrer' => $this->when($request->user()?->hasRole('admin'), $this->referrer),

            'read_at' => $this->read_at?->format('Y-m-d H:i:s'),
            'replied_at' => $this->replied_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            'is_new' => $this->isNew(),
            'is_read' => $this->isRead(),
            'is_resolved' => $this->isResolved(),
            'is_closed' => $this->isClosed(),

            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(
                fn (ContactMessageLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action->value,
                    'action_label' => $log->action->label(),
                    /* Statuses are stored as values, so they are translated
                       here, on the way out, for whoever is reading now. */
                    'old_value' => $this->logValue($log, $log->old_value),
                    'new_value' => $this->logValue($log, $log->new_value),
                    'admin_name' => $log->admin?->name,
                    'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                ],
            )),
        ];
    }

    /**
     * A logged value as it should read now.
     *
     * A status was written as its value and has a label in the language being
     * read; a salesperson's name and a note were written as text and stand as
     * they are.
     */
    private function logValue(ContactMessageLog $log, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $isStatus = in_array($log->action, [
            ContactLogActionEnum::STATUS_CHANGED,
            ContactLogActionEnum::RECEIVED,
        ], true);

        return $isStatus
            ? (ContactStatusEnum::tryFrom($value)?->label() ?? $value)
            : $value;
    }
}
