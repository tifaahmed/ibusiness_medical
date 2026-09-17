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
     * An enquiry sales looked at and will not pursue — a facility that fails
     * the join-request check, a card-popup number that turns out bogus. Its
     * own end state, not a flavour of `closed`: the pipeline should still say
     * why an enquiry stopped moving, and "we said no" is a different answer
     * from "we finished".
     */
    case REJECTED = 'rejected';

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
     * Translated for whoever is reading now, not whoever wrote it — a status
     * is stored as its value precisely so the label can move with the locale.
     */
    public function label(): string
    {
        return __('admin.contact_messages.'.$this->value);
    }
}
