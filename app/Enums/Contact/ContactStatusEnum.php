<?php

namespace App\Enums\Contact;

/**
 * Where an enquiry sits in the sales pipeline.
 *
 * This is the vocabulary the Deilar dashboard worked its enquiries with before
 * they moved here — a pipeline (somebody is on it, it came to something, it is
 * done) rather than an inbox's read/unread. `new` is deliberately the same
 * string the older inbox used, so the public form at `POST /api/contact-messages`
 * keeps writing a valid status without being touched.
 */
enum ContactStatusEnum: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

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
            self::NEW->value => ['value' => self::NEW->value, 'label' => 'New'],
            self::IN_PROGRESS->value => ['value' => self::IN_PROGRESS->value, 'label' => 'In progress'],
            self::RESOLVED->value => ['value' => self::RESOLVED->value, 'label' => 'Resolved'],
            self::CLOSED->value => ['value' => self::CLOSED->value, 'label' => 'Closed'],
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
