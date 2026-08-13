<?php

namespace App\Support;

/**
 * Single source of truth for a fresh card template's default field positions,
 * sizes, colours and font sizes — served to the admin UI via
 * AdminCardTemplateLayoutDefaultsController so these can be tuned here without
 * a frontend redeploy.
 *
 * x/y/width/height are fractions of the card (0..1), so a layout renders at any
 * size. `font_size` is absolute pixels measured on a {@see self::EDITOR_WIDTH}px
 * wide canvas — a renderer working at another width scales it by
 * (renderWidth / EDITOR_WIDTH). `direction` doubles as horizontal alignment:
 * ltr → left, rtl → right, center → centred.
 *
 * The values below are the Deilar card, measured off
 * public/images/cards/deilar-card-full.png (1579 × 996) and divided through.
 */
class CardTemplateLayoutDefaults
{
    /**
     * Width of the layout editor's preview canvas, in raw unscaled pixels —
     * the canvas an admin is looking at when they pick a `font_size`. Stored
     * font sizes are relative to this width; see the class docblock.
     */
    public const EDITOR_WIDTH = 700;

    /** Text fields — carry font/colour/direction on top of the box. */
    public const TEXT_FIELDS = ['slogan', 'membership_number', 'facebook', 'website', 'phone'];

    /** Fields whose value is an image URL. */
    public const IMAGE_FIELDS = ['logo', 'partner_logo'];

    /** Fields generated from a payload rather than positioned artwork. */
    public const CODE_FIELDS = ['qrcode', 'barcode'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function layout(): array
    {
        return [
            // Brand lockup on the left column — artwork px 174,127 · 363×233,
            // where the Deilar logo sits on the reference card.
            'logo' => [
                'x' => 0.1102, 'y' => 0.1275, 'width' => 0.2299, 'height' => 0.2339,
            ],
            // The tagline under the logo — artwork px 173,505 · 358×51.
            'slogan' => [
                'x' => 0.1096, 'y' => 0.5070, 'width' => 0.2267, 'height' => 0.0512,
                'font_family' => 'Tajawal', 'direction' => 'center', 'color' => '#0b1632', 'font_size' => 22,
            ],

            // The number under the bars — artwork px 823,445 · 389×23.
            'membership_number' => [
                'x' => 0.5212, 'y' => 0.4468, 'width' => 0.2464, 'height' => 0.0231,
                'font_family' => 'Tajawal', 'direction' => 'center', 'color' => '#000000', 'font_size' => 10.6,
            ],

            // The three lines right of the icons printed on the artwork, each
            // centred on its icon (icon centres: y 686.5 / 778.5 / 870).
            'facebook' => [
                'x' => 0.4680, 'y' => 0.6702, 'width' => 0.4940, 'height' => 0.0382,
                'font_family' => 'Tajawal', 'direction' => 'ltr', 'color' => '#000000', 'font_size' => 16.8,
            ],
            'website' => [
                'x' => 0.4680, 'y' => 0.7626, 'width' => 0.4940, 'height' => 0.0382,
                'font_family' => 'Tajawal', 'direction' => 'ltr', 'color' => '#000000', 'font_size' => 16.8,
            ],
            'phone' => [
                'x' => 0.4680, 'y' => 0.8544, 'width' => 0.4940, 'height' => 0.0382,
                'font_family' => 'Tajawal', 'direction' => 'ltr', 'color' => '#000000', 'font_size' => 16.8,
            ],

            // Sits above the "Health Care" line printed on the artwork (y 860).
            'partner_logo' => [
                'x' => 0.1393, 'y' => 0.6175, 'width' => 0.1875, 'height' => 0.2359,
            ],

            'qrcode' => [
                'x' => 0.5516, 'y' => 0.0392, 'width' => 0.1710, 'height' => 0.2741,
            ],
            'barcode' => [
                'x' => 0.5212, 'y' => 0.3394, 'width' => 0.2464, 'height' => 0.1054,
            ],
        ];
    }

    /**
     * Template-level sample values: the content that is fixed for every card
     * cut from this template (the contact lines), plus placeholders for the
     * per-member fields so a preview can be rendered before any member exists.
     *
     * @return array<string, string>
     */
    public static function sampleData(): array
    {
        return [
            'logo' => '/images/logo/dielar.png',
            'slogan' => 'صحتك واكتر',
            'membership_number' => 'MEM-1000',
            'facebook' => 'www.facebook.com/IEasybusiness',
            'website' => 'www.deilar.com',
            'phone' => '01020709993',
            'barcode' => 'MEM-1000',
            'qrcode' => PublicMembershipUrl::qrForSlug('mem-1000'),
            'qrcode_color' => '#000000',
        ];
    }

    /**
     * Every field key the layout knows about, in render order.
     *
     * @return array<string>
     */
    public static function fields(): array
    {
        return [...self::TEXT_FIELDS, ...self::IMAGE_FIELDS, ...self::CODE_FIELDS];
    }
}
