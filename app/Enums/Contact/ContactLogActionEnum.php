<?php

namespace App\Enums\Contact;

/**
 * The kinds of change recorded against an enquiry.
 *
 * `RECEIVED` is written by the form itself, with no admin behind it — which is
 * why `contact_message_logs.admin_id` is nullable.
 */
enum ContactLogActionEnum: string
{
    case RECEIVED = 'received';
    case STATUS_CHANGED = 'status_changed';
    case SALES_ASSIGNED = 'sales_assigned';
    case NOTE_UPDATED = 'note_updated';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, array{value: string, label: string}>
     */
    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => ['value' => $case->value, 'label' => $case->label()]])
            ->all();
    }

    public static function getLabel(string $value): ?string
    {
        return self::tryFrom($value)?->label();
    }

    /**
     * Translated for whoever is reading the activity trail now.
     */
    public function label(): string
    {
        return __('admin.contact_messages.log_actions.'.$this->value);
    }
}
