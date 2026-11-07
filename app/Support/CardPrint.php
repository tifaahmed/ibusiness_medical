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

    /** The sheet layout (position and size of every card on the A4 sheet, strip for the pin, margins) — one JSON setting. */
    public const SHEET = 'card_sheet_layout';

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

    /** @return array{width: float, height: float, back_width: float, back_height: float, sheet: array} */
    public static function toArray(): array
    {
        return ['width' => self::width(), 'height' => self::height(), 'back_width' => self::backWidth(), 'back_height' => self::backHeight(), 'sheet' => self::sheet()];
    }

    /**
     * The saved sheet layout, cleaned. Same limits as `normalizeSheet` in resources/js/.../sheetGeometry.js (lengths in cm):
     * { top_reserve, side_margin, pin_y, pin_dx, guides, h_lines: [dy…], v_lines: [dx…], cards: [ { dx, dy, w, h } … ] }. Nothing saved = today's layout.
     *
     * @return array{top_reserve: float, side_margin: float, pin_y: float, guides: bool, cards: list<array{dx: float, dy: float, w: float|null, h: float|null}>}
     */
    public static function sheet(): array
    {
        return self::normalizeSheet(SiteSettings::get(self::SHEET, []));
    }

    /** @return array same shape as sheet() */
    public static function normalizeSheet(mixed $raw): array
    {
        $s = is_array($raw) ? $raw : [];
        $num = fn ($v, $fallback) => (is_numeric($v) && is_finite((float) $v)) ? (float) $v : $fallback;
        $clamp = fn (float $v, float $lo, float $hi) => round(max($lo, min($hi, $v)), 3);

        $cards = [];
        foreach (array_slice(array_values(is_array($s['cards'] ?? null) ? $s['cards'] : []), 0, 40) as $c) {
            $c = is_array($c) ? $c : [];
            $w = $num($c['w'] ?? null, null);
            $h = $num($c['h'] ?? null, null);
            $cards[] = [
                'dx' => $clamp($num($c['dx'] ?? 0, 0.0), -21.0, 21.0),
                'dy' => $clamp($num($c['dy'] ?? 0, 0.0), -29.7, 29.7),
                'w' => $w === null ? null : $clamp($w, 1.0, 21.0),
                'h' => $h === null ? null : $clamp($h, 1.0, 29.7),
            ];
        }

        $lines = fn ($arr, int $max) => array_map(fn ($v) => $clamp($num($v, 0.0), -15.0, 15.0), array_slice(array_values(is_array($arr) ? $arr : []), 0, $max));

        return [
            'h_lines' => $lines($s['h_lines'] ?? null, 12),
            'v_lines' => $lines($s['v_lines'] ?? null, 4),
            'pin_dx' => $clamp($num($s['pin_dx'] ?? null, 0.0), -10.0, 10.0),
            'top_reserve' => $clamp($num($s['top_reserve'] ?? null, 1.5), 0.2, 6.0),
            'side_margin' => $clamp($num($s['side_margin'] ?? null, 0.25), 0.0, 5.0),
            'pin_y' => $clamp($num($s['pin_y'] ?? null, 0.4), 0.1, 5.0),
            'guides' => array_key_exists('guides', $s) ? (bool) $s['guides'] : true,
            'cards' => $cards,
        ];
    }

    /**
     * The printed card sizes (cm), saved from the sheet editor's first panel into the same four settings the settings page edits.
     * Same limits as the getters; the back can never be bigger than the front.
     *
     * @param  array{width?: mixed, height?: mixed, back_width?: mixed, back_height?: mixed}  $raw
     * @return array{width: float, height: float, back_width: float, back_height: float}
     */
    public static function savePrint(array $raw): array
    {
        $rows = [
            'width' => [self::WIDTH_CM, 'Printed card FRONT width (cm)', 3.0, 20.0],
            'height' => [self::HEIGHT_CM, 'Printed card FRONT height (cm)', 2.0, 28.0],
            'back_width' => [self::BACK_WIDTH_CM, 'Printed card BACK width (cm)', 1.0, 20.0],
            'back_height' => [self::BACK_HEIGHT_CM, 'Printed card BACK height (cm)', 1.0, 28.0],
        ];
        foreach ($rows as $key => [$slug, $name, $lo, $hi]) {
            if (isset($raw[$key]) && is_numeric($raw[$key]) && (float) $raw[$key] > 0) {
                SiteSettings::put($slug, round(max($lo, min($hi, (float) $raw[$key])), 2), Setting::TYPE_NUMBER, $name);
            }
        }

        return ['width' => self::width(), 'height' => self::height(), 'back_width' => self::backWidth(), 'back_height' => self::backHeight()];
    }

    /** Clean, store (one setting row, created on first save) and return the layout. */
    public static function saveSheet(mixed $raw): array
    {
        $clean = self::normalizeSheet($raw);
        SiteSettings::put(self::SHEET, $clean, Setting::TYPE_JSON, ['en' => 'Printed card sheet layout (edited on the batch "Sheet editor" page)', 'ar' => 'تخطيط ورقة طباعة الكروت (يتعدل من صفحة محرر الورقة في الدفعة)']);

        return $clean;
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
