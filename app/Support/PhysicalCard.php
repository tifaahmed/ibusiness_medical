<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\Setting;

/**
 * The PHYSICAL (plastic) card a primary member orders for themselves and their relatives. Edited at /admin/setting.
 *
 *   physical_card_price           what ONE card costs (the same number may be bought several times)
 *   physical_card_shipping_price  what shipping the order costs, charged once per order
 *
 * Who may order is decided HERE, never by a client: a PRIMARY member (not a relative) whose card is PAID and current,
 * whichever plan (yearly or half-year). A trial card, an expired one, or a relative's, may not.
 */
final class PhysicalCard
{
    public const PRICE = 'physical_card_price';

    public const SHIPPING = 'physical_card_shipping_price';

    /** `orders.source` of an order made from this screen. */
    public const SOURCE = 'physical_card';

    /** The most copies of one card number in one order. */
    public const MAX_PER_CARD = 10;

    public static function price(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::PRICE, 50), 2));
    }

    public static function shipping(): float
    {
        return max(0.0, round((float) SiteSettings::get(self::SHIPPING, 50), 2));
    }

    /** @return array{ok: bool, reason: ?string} `reason`: no_card | relative | not_current */
    public static function eligibility(?Membership $card): array
    {
        if ($card === null) {
            return ['ok' => false, 'reason' => 'no_card'];
        }

        if ($card->isRelative()) {
            return ['ok' => false, 'reason' => 'relative'];
        }

        if ($card->isTrial() || ! $card->isCurrent()) {
            return ['ok' => false, 'reason' => 'not_current'];
        }

        return ['ok' => true, 'reason' => null];
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::PRICE, 'name' => ['en' => 'Physical card price (EGP, per card)', 'ar' => 'سعر الكارت الفيزيكال (ج.م للكارت)'], 'value' => '50', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::SHIPPING, 'name' => ['en' => 'Physical card shipping price (EGP, per order)', 'ar' => 'سعر شحن الكارت الفيزيكال (ج.م للطلب)'], 'value' => '50', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
