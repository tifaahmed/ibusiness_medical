<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The contact points the public sites link to: phone, WhatsApp, Facebook page
 * and email.
 *
 * Owned HERE and edited at /admin/setting. The Deilar website draws them in its
 * floating dock, footer and contact page, and the mobile app on its support
 * screen — both read this one set, so a new number is one edit, not a deploy
 * and not two edits that can disagree.
 *
 * A blank value is a channel taken off the site, so nothing falls back to
 * anything: a client simply shows no button for it.
 */
class ShopContact
{
    public const PHONE = 'contact_phone';

    public const PHONE_INTERNATIONAL = 'contact_phone_international';

    public const WHATSAPP = 'contact_whatsapp';

    public const FACEBOOK = 'contact_facebook_url';

    public const EMAIL = 'contact_email';

    /**
     * The values as stored, each a string or null.
     *
     * @return array{phone: ?string, phone_international: ?string, whatsapp: ?string, facebook_url: ?string, email: ?string}
     */
    public static function current(): array
    {
        $text = fn (string $slug): ?string => ($value = trim((string) SiteSettings::get($slug, ''))) === '' ? null : $value;

        return [
            'phone' => $text(self::PHONE),
            'phone_international' => $text(self::PHONE_INTERNATIONAL),
            'whatsapp' => $text(self::WHATSAPP),
            'facebook_url' => $text(self::FACEBOOK),
            'email' => $text(self::EMAIL),
        ];
    }

    /**
     * @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}>
     */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::PHONE, 'name' => ['en' => 'Contact phone (as printed)', 'ar' => 'هاتف التواصل (كما يُعرض)'], 'value' => '01020709993', 'value_type' => Setting::TYPE_PHONE],
            ['slug' => self::PHONE_INTERNATIONAL, 'name' => ['en' => 'Contact phone (international, for dialling)', 'ar' => 'هاتف التواصل (دولي للاتصال)'], 'value' => '+201020709993', 'value_type' => Setting::TYPE_PHONE],
            ['slug' => self::WHATSAPP, 'name' => ['en' => 'WhatsApp number', 'ar' => 'رقم واتساب'], 'value' => '201020709993', 'value_type' => Setting::TYPE_PHONE],
            ['slug' => self::FACEBOOK, 'name' => ['en' => 'Facebook page', 'ar' => 'صفحة فيسبوك'], 'value' => 'https://www.facebook.com/deilarcard', 'value_type' => Setting::TYPE_URL],
            ['slug' => self::EMAIL, 'name' => ['en' => 'Contact email', 'ar' => 'البريد الإلكتروني للتواصل'], 'value' => 'info@deilar.com', 'value_type' => Setting::TYPE_EMAIL],
        ];
    }
}
