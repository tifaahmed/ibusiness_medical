<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The size a card is PRINTED at, and how much smaller its back is drawn. Edited at /admin/setting.
 *
 * Used by the batch PDFs (fronts, front + back, flipped backs). The artwork is the same whatever the size, so changing
 * these never alters a card, only the paper it lands on.
 */
final class CardPrint
{
    public const WIDTH_CM = 'card_print_width_cm';

    public const HEIGHT_CM = 'card_print_height_cm';

    /** The back's own printed size; it is centred in the front's slot, so the two stay behind each other. */
    public const BACK_WIDTH_CM = 'card_back_width_cm';

    public const BACK_HEIGHT_CM = 'card_back_height_cm';

    public static function width(): float
    {
        return self::cm(self::WIDTH_CM, 8.5, 3.0, 20.0);
    }

    public static function height(): float
    {
        return self::cm(self::HEIGHT_CM, 5.5, 2.0, 28.0);
    }

    public static function backWidth(): float
    {
        return min(self::cm(self::BACK_WIDTH_CM, 8.0, 1.0, 20.0), self::width());
    }

    public static function backHeight(): float
    {
        return min(self::cm(self::BACK_HEIGHT_CM, 5.0, 1.0, 28.0), self::height());
    }

    /** @return array{width: float, height: float, back_width: float, back_height: float} */
    public static function toArray(): array
    {
        return ['width' => self::width(), 'height' => self::height(), 'back_width' => self::backWidth(), 'back_height' => self::backHeight()];
    }

    private static function cm(string $slug, float $default, float $min, float $max): float
    {
        $value = (float) SiteSettings::get($slug, $default);

        return $value > 0 ? max($min, min($max, round($value, 2))) : $default;
    }

    /** @return array<int, array{slug: string, name: array<string, string>, value: string, value_type: string}> */
    public static function seedRows(): array
    {
        return [
            ['slug' => self::WIDTH_CM, 'name' => ['en' => 'Printed card FRONT width (cm)', 'ar' => 'عرض وجه الكارت عند الطباعة (سم)'], 'value' => '8.5', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::HEIGHT_CM, 'name' => ['en' => 'Printed card FRONT height (cm)', 'ar' => 'ارتفاع وجه الكارت عند الطباعة (سم)'], 'value' => '5.5', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::BACK_WIDTH_CM, 'name' => ['en' => 'Printed card BACK width (cm)', 'ar' => 'عرض ظهر الكارت عند الطباعة (سم)'], 'value' => '8', 'value_type' => Setting::TYPE_NUMBER],
            ['slug' => self::BACK_HEIGHT_CM, 'name' => ['en' => 'Printed card BACK height (cm)', 'ar' => 'ارتفاع ظهر الكارت عند الطباعة (سم)'], 'value' => '5', 'value_type' => Setting::TYPE_NUMBER],
        ];
    }
}
