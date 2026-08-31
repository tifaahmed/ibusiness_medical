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
        return [
            self::RECEIVED->value => ['value' => self::RECEIVED->value, 'label' => 'Received'],
            self::STATUS_CHANGED->value => ['value' => self::STATUS_CHANGED->value, 'label' => 'Status changed'],
            self::SALES_ASSIGNED->value => ['value' => self::SALES_ASSIGNED->value, 'label' => 'Salesperson assigned'],
            self::NOTE_UPDATED->value => ['value' => self::NOTE_UPDATED->value, 'label' => 'Note updated'],
        ];
    }

    public static function getLabel(string $value): ?string
    {
        return self::getOptions()[$value]['label'] ?? null;
    }

    public function label(): string
    {
        return self::getLabel($this->value) ?? $this->value;
    }
}
