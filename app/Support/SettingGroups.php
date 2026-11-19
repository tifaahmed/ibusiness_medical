<?php

namespace App\Support;

/**
 * Which section of the settings list a row belongs to.
 *
 * Purely presentational: it reads the key's prefix and changes nothing in the
 * database, so a new setting needs no migration — an unknown key falls into
 * "Other". The order here is the order the sections are drawn in, and the
 * FIRST matching prefix wins (so `quick_trial_days_*` is filed with the
 * quick-add messages, not with the memberships).
 */
class SettingGroups
{
    /** key => [label ar, label en, prefixes] */
    private const GROUPS = [
        'contact' => ['بيانات التواصل', 'Contact details', ['contact_']],
        'membership' => ['العضوية: الباقات والأسعار', 'Membership plans & prices', ['membership_']],
        'relatives' => ['رسوم الأقارب', 'Relatives fee', ['relative_']],
        'referral' => ['الإحالات (عضو يجيب عضو)', 'Referrals', ['referral_']],
        'loyalty' => ['نقاط الولاء واللعبة', 'Loyalty points & quiz', ['loyalty_']],
        'wallet' => ['المحفظة والسحب', 'Wallet & cash-out', ['wallet_']],
        'pay_products' => ['طرق الدفع — المنتجات', 'Payment methods — shop', ['payment_products_']],
        'pay_cards' => ['طرق الدفع — الكروت الفعلية', 'Payment methods — physical cards', ['payment_cards_']],
        'pay_membership' => ['طرق الدفع — الاشتراك', 'Payment methods — membership', ['payment_membership_']],
        'pay_main' => ['طرق الدفع — المفاتيح الرئيسية', 'Payment methods — main switches', ['payment_']],
        'shop' => ['المتجر والتوصيل', 'Shop & delivery', ['shop_']],
        'cards' => ['الكروت الفعلية والطباعة', 'Physical cards & printing', ['physical_card_', 'card_print_', 'card_back_']],
        'otp' => ['رسائل الموبايل وكود الدخول', 'SMS & login codes', ['otp_', 'sms_']],
        'quick' => ['الإضافة السريعة ورسائل واتساب', 'Quick add & WhatsApp messages', ['quick_', 'wa_']],
        'other' => ['أخرى', 'Other', []],
    ];

    public static function keyFor(string $slug): string
    {
        foreach (self::GROUPS as $key => [, , $prefixes]) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($slug, $prefix)) {
                    return $key;
                }
            }
        }

        return 'other';
    }

    public static function position(string $key): int
    {
        $i = array_search($key, array_keys(self::GROUPS), true);

        return $i === false ? count(self::GROUPS) : $i;
    }

    /** @return array{key:string,label:array{ar:string,en:string}} */
    public static function describe(string $slug): array
    {
        $key = self::keyFor($slug);
        [$ar, $en] = self::GROUPS[$key];

        return ['key' => $key, 'label' => ['ar' => $ar, 'en' => $en]];
    }
}
