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
    /** A trial member asking customer services to ring them and add their relatives. */
    case RELATIVE_REQUEST = 'relative_request';
    /** A member who wants to pay for the card through customer services (cash, Vodafone Cash, Instapay). */
    case SUBSCRIPTION_REQUEST = 'subscription_request';
    /** A member asking for their money back on a paid order that has not shipped yet. */
    case REFUND_REQUEST = 'refund_request';
    /** A relative added by the card owner that waits for an admin's approval. */
    case PENDING_RELATIVE = 'pending_relative';
    /** A member asking an admin to change the national ID on their card, with the new number and photos of the ID. */
    case NATIONAL_ID_CHANGE = 'national_id_change';
    /** A member recommending a service provider (medical / restaurant / entertainment) for us to ring and sign up; the member gets the discount. */
    case PROVIDER_REFERRAL = 'provider_referral';

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
