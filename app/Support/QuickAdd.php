<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\Setting;

/**
 * Quick add: settings and the ONE place the customer's welcome SMS is written.
 *
 * The text is deliberately short (every 70 Arabic characters is one billed message) and carries NO prices and NO
 * membership number: the customer signs in to the app with their phone number. Days come from settings.
 */
final class QuickAdd
{
    public const SMS_ENABLED = 'sms_sending_enabled';

    public const TRIAL_DAYS_INDIVIDUAL = 'quick_trial_days_individual';

    public const TRIAL_DAYS_COMPANY = 'quick_trial_days_company';

    public const DEVICE_ANDROID = 'android';

    public const DEVICE_IPHONE = 'iphone';

    /** @return list<string> */
    public static function devices(): array
    {
        return [self::DEVICE_ANDROID, self::DEVICE_IPHONE];
    }

    /** The master switch for every message this feature writes. OFF = messages are only recorded as queued. */
    public static function smsEnabled(): bool
    {
        return SiteSettings::get(self::SMS_ENABLED, false) === true;
    }

    public static function trialDays(string $kind): int
    {
        return $kind === Membership::KIND_COMPANY
            ? max(1, (int) SiteSettings::get(self::TRIAL_DAYS_COMPANY, 30))
            : max(1, (int) SiteSettings::get(self::TRIAL_DAYS_INDIVIDUAL, 7));
    }

    /** The store link for this phone, or null when none is set (the message then simply has no link). */
    public static function storeLink(string $device): ?string
    {
        $url = trim((string) ($device === self::DEVICE_IPHONE ? config('mobile_app.ios_store_url') : config('mobile_app.store_url')));

        return $url === '' ? null : $url;
    }

    /** "7 أيام", "30 يوماً" */
    public static function daysPhrase(int $days): string
    {
        return match (true) {
            $days === 1 => 'يوم',
            $days === 2 => 'يومين',
            $days <= 10 => $days.' أيام',
            default => $days.' يوماً',
        };
    }

    /**
     * The customer's text. With a null kind or device (only the live preview passes those, while the form is
     * still being filled) the missing piece shows as a [bracketed placeholder]; a real send always has both.
     */
    public static function message(?string $kind, ?string $device, bool $wantsCard, ?int $days = null): string
    {
        $app = (string) config('mobile_app.name_ar', 'ديلر');

        if ($kind === null) {
            $body = 'أهلاً بك في '.$app.'! لديك تجربة مجانية [المدة] [وباقي النص حسب النوع: فرد أو شركة]';
        } else {
            $days ??= self::trialDays($kind);
            $body = $kind === Membership::KIND_COMPANY
                ? 'أهلاً بك في '.$app.'! لديك تجربة مجانية '.self::daysPhrase($days).'، وبعدها إذا وافقتم على المتابعة يتم الخصم.'
                : 'أهلاً بك في '.$app.'! لديك تجربة مجانية '.self::daysPhrase($days).'، وبعدها يلزم الاشتراك من التطبيق أو عن طريقنا.';
        }

        if ($wantsCard) {
            $body .= ' مندوبنا سيتواصل معك قريباً لتوصيل الكارت.';
        }

        if ($device === null) {
            return $body.' سجّل دخولك برقم تليفونك: [رابط التطبيق حسب نوع الهاتف]';
        }

        $link = self::storeLink($device);

        return $body.($link !== null
            ? ' سجّل دخولك برقم تليفونك: '.$link
            : ' سجّل دخولك بتطبيق '.$app.' برقم تليفونك.');
    }

    /** @return array{characters: int, segments: int} Arabic is UCS-2: 70 characters in one message, 67 per part when joined. */
    public static function size(string $body): array
    {
        $n = mb_strlen($body);

        return ['characters' => $n, 'segments' => $n <= 70 ? 1 : (int) ceil($n / 67)];
    }

    /** @return list<array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::SMS_ENABLED, 'name' => ['en' => 'Send SMS to customers (OFF = messages only queue)', 'ar' => 'إرسال الرسائل للعملاء (مقفول = تتسجل فقط ولا تُرسل)'], 'value' => '0', 'value_type' => Setting::TYPE_BOOLEAN],
            ['slug' => self::TRIAL_DAYS_INDIVIDUAL, 'name' => ['en' => 'Quick add — trial days for individuals', 'ar' => 'الإضافة السريعة — أيام التجربة للأفراد'], 'value' => '7', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::TRIAL_DAYS_COMPANY, 'name' => ['en' => 'Quick add — trial days for companies', 'ar' => 'الإضافة السريعة — أيام التجربة للشركات'], 'value' => '30', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
