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
        return [
            self::CONTACT_FORM->value => ['value' => self::CONTACT_FORM->value, 'label' => 'Contact form'],
            self::CARD_POPUP->value => ['value' => self::CARD_POPUP->value, 'label' => 'Card popup'],
            self::JOIN_REQUEST->value => ['value' => self::JOIN_REQUEST->value, 'label' => 'Join request'],
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
