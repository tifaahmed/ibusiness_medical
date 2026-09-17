<?php

namespace App\Enums\Contact;

/**
 * Which public form an enquiry came in through.
 *
 * Sales work the three very differently: a contact form enquiry arrives with
 * the visitor's own words, a card popup enquiry is a bare phone number that
 * wants a call back, and a join request is a medical facility applying to the
 * network — the only one that carries a commercial register number to verify.
 */
enum ContactSourceEnum: string
{
    case CONTACT_FORM = 'contact_form';
    case CARD_POPUP = 'card_popup';
    case JOIN_REQUEST = 'join_request';

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
     * Translated for whoever is reading now, same as the status pipeline.
     */
    public function label(): string
    {
        return __('admin.contact_messages.'.$this->value);
    }
}
