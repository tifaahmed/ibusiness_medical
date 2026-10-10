<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The shop's money settings: delivery, and the wallet a transfer goes to.
 *
 * Owned HERE, by the membership system, and edited at /admin/setting like the
 * rest of the site's configuration. Every order is written with THESE figures
 * whoever placed it — the Deilar website, the mobile app — and both clients
 * read them from this application, so there is exactly one place shipping and
 * the wallet number come from:
 *
 *   shop_delivery_cost             what the courier costs the shop
 *   shop_delivery_price            what the buyer is charged
 *   shop_free_delivery_threshold   a basket at or above this ships free;
 *                                  leave the value empty for "never free"
 *   shop_wallet_number             the phone a wallet transfer goes to
 *
 * A row that has not been created yet falls back to `config/mobile_orders.php`
 * (the install's defaults), so a fresh install works before anyone opens the
 * settings page.
 */
class ShopDelivery
{
    public const COST = 'shop_delivery_cost';

    public const PRICE = 'shop_delivery_price';

    public const THRESHOLD = 'shop_free_delivery_threshold';

    public const WALLET = 'shop_wallet_number';


    /** The previous names, from when only the mobile app used these. */
    public const LEGACY_SLUGS = [
        self::COST => 'mobile_delivery_cost',
        self::PRICE => 'mobile_delivery_price',
        self::THRESHOLD => 'mobile_free_delivery_threshold',
    ];

    /**
     * Settings the admin has not filled in yet that customers would feel, each
     * with where to fix it — shown as a notice across the admin until it is done.
     *
     * Only what breaks something for a buyer is listed: with no wallet number
     * the wallet-transfer choice has nowhere to send money, so the apps hide it.
     *
     * @return list<array{slug: string, message: string, url: string}>
     */
    public static function missingSetup(): array
    {
        $missing = [];

        if (self::wallet() === null) {
            $setting = Setting::query()->where('slug', self::WALLET)->first();

            $missing[] = [
                'slug' => self::WALLET,
                'message' => app()->getLocale() === 'ar'
                    ? 'لم تُضف رقم المحفظة بعد — خيار «تحويل محفظة» لن يظهر للعملاء حتى تضيفه.'
                    : 'The wallet number is not set — the "wallet transfer" choice stays hidden from customers until you add it.',
                'url' => $setting !== null ? route('admin.setting.edit', $setting) : route('admin.setting.create'),
            ];
        }

        return $missing;
    }

    /** The phone a wallet transfer goes to, or null when none is set. */
    public static function wallet(): ?string
    {
        $number = trim((string) SiteSettings::get(self::WALLET, ''));

        return $number === '' ? null : $number;
    }

    /**
     * @return array{cost: float, price: float, threshold: ?float}
     */
    public static function current(): array
    {
        $threshold = SiteSettings::has(self::THRESHOLD)
            ? SiteSettings::get(self::THRESHOLD)
            : config('mobile_orders.free_delivery_threshold');

        return [
            'cost' => round((float) SiteSettings::get(self::COST, config('mobile_orders.delivery_cost')), 2),
            'price' => round((float) SiteSettings::get(self::PRICE, config('mobile_orders.delivery_price')), 2),
            'threshold' => is_numeric($threshold) ? round((float) $threshold, 2) : null,
        ];
    }

    /**
     * The rows the admin edits, with this install's defaults.
     *
     * @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}>
     */
    public static function seedRows(): array
    {
        return [
            [
                'slug' => self::COST,
                'name' => ['en' => 'Delivery cost (to the shop)', 'ar' => 'تكلفة التوصيل على الشركة'],
                'value' => (string) config('mobile_orders.delivery_cost'),
                'value_type' => Setting::TYPE_NUMBER,
            ],
            [
                'slug' => self::PRICE,
                'name' => ['en' => 'Delivery price (charged to the buyer)', 'ar' => 'سعر التوصيل للعميل'],
                'value' => (string) config('mobile_orders.delivery_price'),
                'value_type' => Setting::TYPE_NUMBER,
            ],
            [
                'slug' => self::THRESHOLD,
                'name' => ['en' => 'Free delivery from (EGP, empty = never free)', 'ar' => 'توصيل مجاني للطلبات من (ج.م، فارغ = لا يوجد)'],
                'value' => (string) config('mobile_orders.free_delivery_threshold'),
                'value_type' => Setting::TYPE_NUMBER,
            ],
            [
                'slug' => self::WALLET,
                'name' => ['en' => 'Wallet number (transfers go here)', 'ar' => 'رقم المحفظة (التحويلات)'],
                'value' => '',
                'value_type' => Setting::TYPE_PHONE,
            ],
        ];
    }
}
