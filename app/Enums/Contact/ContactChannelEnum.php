<?php

namespace App\Enums\Contact;

use Illuminate\Http\Request;

/**
 * Which client the enquiry was made from: the Deilar website or the mobile
 * app. Not the same as `source` (which FORM it was) — a card popup can be
 * filled in on either.
 */
enum ContactChannelEnum: string
{
    case WEBSITE = 'website';
    case MOBILE = 'mobile';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function getOptions(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }

    public function label(): string
    {
        return __('admin.contact_messages.channel_'.$this->value);
    }

    /**
     * For the member API, which both clients call. The website reaches it from
     * its own server (an HTTP client's user agent); the app calls it directly.
     */
    public static function fromRequest(Request $request): self
    {
        return preg_match('/guzzle|laravel|curl|php|python|node|axios/i', (string) $request->userAgent())
            ? self::WEBSITE
            : self::MOBILE;
    }
}
