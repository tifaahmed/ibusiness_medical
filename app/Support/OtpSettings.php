<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\Sms\SmsGateway;

/**
 * How the storefront's phone login behaves, as an administrator has set it.
 *
 * Four rows in `settings` (seeded by `SettingSeeder`, edited at
 * /admin/setting) drive the whole of it:
 *
 *   otp_sms_enabled   send a real random code by SMS, or don't
 *   otp_fixed_code    the code that stands in when SMS is off
 *   otp_length        how many digits a real code has
 *   otp_ttl_minutes   how long one is good for
 *
 * The settings live HERE rather than on the Deilar site because the whole of
 * the OTP is decided here: Deilar asks for a code and checks one, and is never
 * told which mode this is in or what the code is. A partner that could choose
 * its own OTP policy would not be a gate at all.
 *
 * Turning `otp_sms_enabled` off is the testing mode: nothing is sent, and the
 * fixed code (1234 out of the box) is what verifies. The fixed code is refused
 * whenever SMS is on, so leaving one set is not a permanent way past a real
 * code.
 */
final class OtpSettings
{
    public const SMS_ENABLED = 'otp_sms_enabled';

    public const FIXED_CODE = 'otp_fixed_code';

    public const LENGTH = 'otp_length';

    public const TTL_MINUTES = 'otp_ttl_minutes';

    public const MESSAGE = 'otp_message';

    /** What a code looks like when nobody has said otherwise. */
    public const DEFAULT_LENGTH = 4;

    /** Long enough to read an SMS and type it, short enough to expire. */
    public const DEFAULT_TTL_MINUTES = 10;

    public const DEFAULT_FIXED_CODE = '1234';

    /** `:code` is filled in with the digits; anything else is sent as written. */
    public const DEFAULT_MESSAGE = 'Your :app verification code is :code';

    /** Bounds, so an admin typing 400 into the length box cannot break sending. */
    private const MIN_LENGTH = 4;

    private const MAX_LENGTH = 8;

    private const MIN_TTL_MINUTES = 1;

    private const MAX_TTL_MINUTES = 60;

    /**
     * Whether a real code should be generated and sent.
     *
     * Both halves have to agree: the setting says yes AND the gateway has
     * credentials. An install that has never been given any is in fixed-code
     * mode whatever the row says — the alternative is asking a member for a
     * code that nothing could ever have sent them.
     */
    public static function deliversBySms(): bool
    {
        return SiteSettings::get(self::SMS_ENABLED, true) === true
            && SmsGateway::isConfigured();
    }

    /**
     * The code accepted while SMS is off, or null when there is none.
     *
     * Null is a real state and it locks the storefront's login: an admin who
     * clears this box while SMS is also off has decided nobody signs in, and
     * the request endpoint says so rather than quietly minting 1234.
     */
    public static function fixedCode(): ?string
    {
        /*
         * `has()` first, because a cleared box and a missing row mean opposite
         * things. An install that has never been seeded should get the default;
         * an administrator who deliberately emptied the field has closed the
         * login, and falling back to 1234 there would quietly reopen it.
         */
        $raw = SiteSettings::has(self::FIXED_CODE)
            ? (string) SiteSettings::get(self::FIXED_CODE, '')
            : self::DEFAULT_FIXED_CODE;

        $code = preg_replace('/\D+/', '', $raw) ?? '';

        return $code === '' ? null : $code;
    }

    /**
     * How many digits a generated code carries.
     */
    public static function length(): int
    {
        $length = (int) SiteSettings::get(self::LENGTH, self::DEFAULT_LENGTH);

        return max(self::MIN_LENGTH, min(self::MAX_LENGTH, $length));
    }

    /**
     * How long a code stays good for, in minutes.
     */
    public static function ttlMinutes(): int
    {
        $minutes = (int) SiteSettings::get(self::TTL_MINUTES, self::DEFAULT_TTL_MINUTES);

        return max(self::MIN_TTL_MINUTES, min(self::MAX_TTL_MINUTES, $minutes));
    }

    /**
     * The SMS body for `$code`, with `:code` and `:app` filled in.
     *
     * Kept as a setting because it is the only thing about this login a member
     * ever reads, and rewording it should not be a deploy. A template that has
     * lost its `:code` still sends — with the code appended, so the message is
     * useless rather than actively wrong.
     */
    public static function message(string $code): string
    {
        $template = trim((string) SiteSettings::get(self::MESSAGE, self::DEFAULT_MESSAGE));

        if ($template === '') {
            $template = self::DEFAULT_MESSAGE;
        }

        if (! str_contains($template, ':code')) {
            $template .= ' :code';
        }

        return strtr($template, [
            ':code' => $code,
            ':app' => (string) SiteSettings::get('deilar_name', config('app.name')),
        ]);
    }

    /**
     * The rows this reads, in the shape `SettingSeeder` writes them.
     *
     * Listed here rather than in the seeder so the defaults above and the rows
     * an install starts with cannot drift apart.
     *
     * @return list<array{slug: string, name: array<string, string>, value: string, value_type: string}>
     */
    public static function seedRows(): array
    {
        return [
            [
                'slug' => self::SMS_ENABLED,
                'name' => ['en' => 'Send login codes by SMS', 'ar' => 'إرسال رمز الدخول برسالة'],
                'value' => '1',
                'value_type' => Setting::TYPE_BOOLEAN,
            ],
            [
                'slug' => self::FIXED_CODE,
                'name' => ['en' => 'Fixed login code (when SMS is off)', 'ar' => 'رمز الدخول الثابت (عند إيقاف الرسائل)'],
                'value' => self::DEFAULT_FIXED_CODE,
                'value_type' => Setting::TYPE_STRING,
            ],
            [
                'slug' => self::LENGTH,
                'name' => ['en' => 'Login code length', 'ar' => 'عدد أرقام رمز الدخول'],
                'value' => (string) self::DEFAULT_LENGTH,
                'value_type' => Setting::TYPE_NUMBER,
            ],
            [
                'slug' => self::TTL_MINUTES,
                'name' => ['en' => 'Login code validity (minutes)', 'ar' => 'صلاحية رمز الدخول (بالدقائق)'],
                'value' => (string) self::DEFAULT_TTL_MINUTES,
                'value_type' => Setting::TYPE_NUMBER,
            ],
            [
                'slug' => self::MESSAGE,
                'name' => ['en' => 'Login code SMS text', 'ar' => 'نص رسالة رمز الدخول'],
                'value' => self::DEFAULT_MESSAGE,
                'value_type' => Setting::TYPE_TEXT,
            ],
        ];
    }
}
