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

    /** Message templates (rows in `settings`, text). Placeholders: {app} the Arabic app name, {days} "7 أيام", {link} the store link. */
    public const MSG_INDIVIDUAL = 'quick_msg_individual';

    public const MSG_COMPANY = 'quick_msg_company';

    public const MSG_CARD = 'quick_msg_card';

    public const MSG_LINK = 'quick_msg_link';

    public const MSG_NO_LINK = 'quick_msg_no_link';

    /** WhatsApp templates (settings rows, multi-line text with WhatsApp formatting: *bold* _italic_ ~strike~). Placeholders: {name} {app} {days} {number} {card_link} {link} {android} {ios}. */
    public const WA_INDIVIDUAL = 'wa_msg_individual';

    public const WA_COMPANY = 'wa_msg_company';

    public const WA_CARD = 'wa_msg_card';

    public const WA_DETAILS = 'wa_msg_details';

    public const WA_LINKS = 'wa_msg_links';

    public const DEVICE_ANDROID = 'android';

    public const DEVICE_IPHONE = 'iphone';

    /** @return array<string, string> slug => the wording used when the row is empty or missing */
    public static function templateDefaults(): array
    {
        return [
            self::MSG_INDIVIDUAL => 'أهلاً بك في {app}! لديك تجربة مجانية {days}، وبعدها يلزم الاشتراك من التطبيق أو عن طريقنا.',
            self::MSG_COMPANY => 'أهلاً بك في {app}! لديك تجربة مجانية {days}، وبعدها إذا وافقتم على المتابعة يتم الخصم.',
            self::MSG_CARD => 'مندوبنا سيتواصل معك قريباً لتوصيل الكارت.',
            self::MSG_LINK => 'سجّل دخولك برقم تليفونك: {link}',
            self::MSG_NO_LINK => 'سجّل دخولك بتطبيق {app} برقم تليفونك.',
        ];
    }

    /** @return array<string, string> */
    public static function whatsappDefaults(): array
    {
        return [
            self::WA_INDIVIDUAL => "أهلاً {name} 👋\n*أهلاً بك في {app}!*\nلديك تجربة مجانية {days}، وبعدها يلزم الاشتراك من التطبيق أو عن طريقنا.",
            self::WA_COMPANY => "أهلاً {name} 👋\n*أهلاً بك في {app}!*\nلديك تجربة مجانية {days}، وبعدها إذا وافقتم على المتابعة يتم الخصم.",
            self::WA_CARD => 'مندوبنا سيتواصل معك قريباً لتوصيل الكارت.',
            self::WA_DETAILS => "رقم العضوية: *{number}*\nكارتك: {card_link}",
            self::WA_LINKS => "حمّل التطبيق:\nأندرويد: {android}\nآيفون: {ios}\nسجّل دخولك برقم تليفونك.",
        ];
    }

    /** @return list<string> */
    public static function whatsappSlugs(): array
    {
        return array_keys(self::whatsappDefaults());
    }

    /**
     * The WhatsApp text for a customer. A line whose link/number is not available is dropped (no "آيفون: " with nothing after it).
     *
     * @param  array{name: string, number: string, card_link: string, kind: string, wants_card: bool, days?: int}  $v
     */
    public static function whatsappMessage(array $v): string
    {
        $app = (string) config('mobile_app.name_ar', 'ديلر');
        $vars = [
            '{name}' => trim($v['name']),
            '{app}' => $app,
            '{days}' => self::daysPhrase($v['days'] ?? self::trialDays($v['kind'])),
            '{number}' => $v['number'],
            '{card_link}' => $v['card_link'],
            '{android}' => (string) self::storeLink(self::DEVICE_ANDROID),
            '{ios}' => (string) self::storeLink(self::DEVICE_IPHONE),
        ];
        $fill = function (string $text) use ($vars): string {
            $lines = [];
            foreach (preg_split('/\R/u', $text) as $line) {
                // Drop a line whose placeholder has no value.
                $empty = false;
                foreach ($vars as $key => $val) {
                    if (str_contains($line, $key) && trim($val) === '') {
                        $empty = true;
                    }
                }
                if (! $empty) {
                    $lines[] = strtr($line, $vars);
                }
            }

            return trim(implode("\n", $lines));
        };
        $wa = fn (string $slug) => trim((string) SiteSettings::get($slug, '')) === '' ? self::whatsappDefaults()[$slug] : (string) SiteSettings::get($slug);

        $blocks = [$fill($wa($v['kind'] === Membership::KIND_COMPANY ? self::WA_COMPANY : self::WA_INDIVIDUAL))];
        if (! empty($v['wants_card'])) {
            $blocks[] = $fill($wa(self::WA_CARD));
        }
        $blocks[] = $fill($wa(self::WA_DETAILS));
        $blocks[] = $fill($wa(self::WA_LINKS));

        return implode("\n\n", array_filter($blocks, fn ($b) => $b !== ''));
    }

    /** @return list<string> */
    public static function templateSlugs(): array
    {
        return array_keys(self::templateDefaults());
    }

    /** The edited wording, or the built-in one when the row is empty — a blank template must never produce a blank SMS. */
    public static function template(string $slug): string
    {
        $value = (string) SiteSettings::get($slug, '');

        return trim($value) === '' ? self::templateDefaults()[$slug] : $value;
    }

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
        $fill = fn (string $text, ?string $link = null, ?string $daysText = null) => strtr($text, ['{app}' => $app, '{days}' => $daysText ?? '[المدة]', '{link}' => $link ?? '[رابط التطبيق حسب نوع الهاتف]']);

        // The sentences are stored trimmed (the settings form trims what is saved), so THIS code puts one space between them.
        if ($kind === null) {
            $parts = [$fill('أهلاً بك في {app}! لديك تجربة مجانية {days} [وباقي النص حسب النوع: فرد أو شركة]')];
        } else {
            $days ??= self::trialDays($kind);
            $parts = [$fill(self::template($kind === Membership::KIND_COMPANY ? self::MSG_COMPANY : self::MSG_INDIVIDUAL), null, self::daysPhrase($days))];
        }

        if ($wantsCard) {
            $parts[] = $fill(self::template(self::MSG_CARD));
        }

        if ($device === null) {
            $parts[] = $fill(self::template(self::MSG_LINK));
        } else {
            $link = self::storeLink($device);
            $parts[] = $link !== null ? $fill(self::template(self::MSG_LINK), $link) : $fill(self::template(self::MSG_NO_LINK));
        }

        return implode(' ', array_map('trim', $parts));
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
            ['slug' => self::MSG_INDIVIDUAL, 'name' => ['en' => 'Quick add message — individuals (opening). {app} {days}', 'ar' => 'رسالة الإضافة السريعة — الأفراد (الافتتاحية). {app} {days}'], 'value' => self::templateDefaults()[self::MSG_INDIVIDUAL], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::MSG_COMPANY, 'name' => ['en' => 'Quick add message — companies (opening). {app} {days}', 'ar' => 'رسالة الإضافة السريعة — الشركات (الافتتاحية). {app} {days}'], 'value' => self::templateDefaults()[self::MSG_COMPANY], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::MSG_CARD, 'name' => ['en' => 'Quick add message — added when the customer wants a card', 'ar' => 'رسالة الإضافة السريعة — تُضاف لو العميل عايز كارت'], 'value' => self::templateDefaults()[self::MSG_CARD], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::MSG_LINK, 'name' => ['en' => 'Quick add message — sign-in line WITH the store link. {link}', 'ar' => 'رسالة الإضافة السريعة — سطر الدخول مع رابط المتجر. {link}'], 'value' => self::templateDefaults()[self::MSG_LINK], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::MSG_NO_LINK, 'name' => ['en' => 'Quick add message — sign-in line when no store link is set. {app}', 'ar' => 'رسالة الإضافة السريعة — سطر الدخول لما مفيش رابط. {app}'], 'value' => self::templateDefaults()[self::MSG_NO_LINK], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::WA_INDIVIDUAL, 'name' => ['en' => 'WhatsApp message — individuals (opening). {name} {app} {days}', 'ar' => 'رسالة الواتس — الأفراد (الافتتاحية). {name} {app} {days}'], 'value' => self::whatsappDefaults()[self::WA_INDIVIDUAL], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::WA_COMPANY, 'name' => ['en' => 'WhatsApp message — companies (opening). {name} {app} {days}', 'ar' => 'رسالة الواتس — الشركات (الافتتاحية). {name} {app} {days}'], 'value' => self::whatsappDefaults()[self::WA_COMPANY], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::WA_CARD, 'name' => ['en' => 'WhatsApp message — added when the customer wants a card', 'ar' => 'رسالة الواتس — تُضاف لو العميل عايز كارت'], 'value' => self::whatsappDefaults()[self::WA_CARD], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::WA_DETAILS, 'name' => ['en' => 'WhatsApp message — card details. {number} {card_link}', 'ar' => 'رسالة الواتس — تفاصيل الكارت. {number} {card_link}'], 'value' => self::whatsappDefaults()[self::WA_DETAILS], 'value_type' => Setting::TYPE_TEXT],
            ['slug' => self::WA_LINKS, 'name' => ['en' => 'WhatsApp message — app links. {android} {ios}', 'ar' => 'رسالة الواتس — روابط التطبيق. {android} {ios}'], 'value' => self::whatsappDefaults()[self::WA_LINKS], 'value_type' => Setting::TYPE_TEXT],
        ];
    }
}
