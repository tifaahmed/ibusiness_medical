<?php

namespace App\Services;

use App\Models\CardTemplate;
use App\Support\ArabicText;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Draws the back of a card from a template's settings: a background artwork
 * with the logo, slogan, title, website and (optionally) a QR code on top, each
 * one hideable. The back holds nothing per-member, so one PNG per template
 * serves every card made from it.
 *
 * The file name carries a hash of everything that feeds it, so an edit simply
 * produces a new file — there is no cache to invalidate.
 */
class CardBackRenderer
{
    private const W = CardGenerationService::CARD_W;

    private const H = 996;

    private const DEFAULT_LOGO = 'images/logo/dielar.png';

    /** Blank decorative artwork used until a template uploads its own. */
    private const DEFAULT_BACKGROUND = 'images/cards/card-back-empty.png';

    /** Hash of the settings and of the uploads they point at. */
    public function signature(CardTemplate $template): string
    {
        $files = [];
        foreach ([$template->back_image ?: self::DEFAULT_BACKGROUND, $template->back_logo] as $path) {
            $abs = $path ? public_path(ltrim($path, '/')) : null;
            $files[] = [$path, $abs && is_file($abs) ? filemtime($abs) : null];
        }

        return substr(md5(json_encode([$template->resolvedBackConfig(), $files, 4])), 0, 12);
    }

    /** Absolute path of the rendered back, drawing it first if needed. */
    public function ensure(CardTemplate $template): ?string
    {
        $relative = $this->relativePath($template);
        $disk = Storage::disk('public');

        if ($disk->exists($relative)) {
            return $disk->path($relative);
        }

        try {
            $path = $this->render($template, $disk->path($relative));
        } catch (\Throwable $e) {
            Log::error('Card back render failed', [
                'card_template_id' => $template->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }

        if ($path) {
            // Older renders of this template are dead weight now.
            foreach ($disk->files('cards/backs') as $file) {
                if ($file !== $relative && str_starts_with(basename($file), "template-{$template->id}-")) {
                    $disk->delete($file);
                }
            }
        }

        return $path;
    }

    public function relativePath(CardTemplate $template): string
    {
        return 'cards/backs/template-'.$template->id.'-'.$this->signature($template).'.png';
    }

    private function render(CardTemplate $template, string $destination): ?string
    {
        $cfg = $template->resolvedBackConfig();
        $W = self::W;
        $H = self::H;

        $image = imagecreatetruecolor($W, $H);
        imagealphablending($image, true);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));

        // Background artwork, stretched over the whole card.
        $background = $template->back_image ?: self::DEFAULT_BACKGROUND;
        if ($bg = $this->load(public_path(ltrim($background, '/')))) {
            imagecopyresampled($image, $bg, 0, 0, 0, 0, $W, $H, imagesx($bg), imagesy($bg));
            imagedestroy($bg);
        }

        if ($cfg['logo']['visible']) {
            $logo = $template->back_logo ?: self::DEFAULT_LOGO;
            [$x, $y, $w, $h] = $this->box($cfg['logo']);
            $this->drawImage($image, public_path(ltrim($logo, '/')), $x, $y, $w, $h);
        }

        foreach (['slogan', 'title', 'website'] as $key) {
            $this->drawText($image, $cfg[$key]);
        }

        if ($cfg['qrcode']['visible'] && trim((string) $cfg['qrcode']['value']) !== '') {
            [$x, $y, $w, $h] = $this->box($cfg['qrcode']);
            $this->drawQr($image, (string) $cfg['qrcode']['value'], (int) $x, (int) $y, (int) max(16, min($w, $h)));
        }

        $dir = dirname($destination);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        imagepng($image, $destination);
        imagedestroy($image);

        return $destination;
    }

    private function load(string $path): ?\GdImage
    {
        if (! is_file($path)) {
            return null;
        }

        $image = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            'gif' => @imagecreatefromgif($path),
            default => @imagecreatefromjpeg($path),
        };

        return $image ?: null;
    }

    /** Contain-fit and centre an image inside a box. */
    private function drawImage(\GdImage $image, string $path, float $x, float $y, float $w, float $h): void
    {
        $src = $this->load($path);
        if (! $src) {
            return;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        $fit = min($w / $sw, $h / $sh);
        $dw = max(1, (int) ($sw * $fit));
        $dh = max(1, (int) ($sh * $fit));

        imagealphablending($image, true);
        imagecopyresampled($image, $src, (int) ($x + ($w - $dw) / 2), (int) ($y + ($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} x, y, width, height in px */
    private function box(array $part): array
    {
        return [
            (float) $part['x'] * self::W,
            (float) $part['y'] * self::H,
            max(1.0, (float) $part['width'] * self::W),
            max(1.0, (float) $part['height'] * self::H),
        ];
    }

    /** Aligned inside its box, auto-shrunk to the box width. */
    private function drawText(\GdImage $image, array $part): void
    {
        $text = trim((string) ($part['text'] ?? ''));
        if (! ($part['visible'] ?? false) || $text === '') {
            return;
        }

        $font = public_path('fonts/Tajawal-Bold.ttf');
        if (! is_file($font)) {
            return;
        }

        [$x, $y, $w, $h] = $this->box($part);

        // font_size is px on a 700px card, like the front; GD wants points.
        $size = (float) ($part['font_size'] ?? 20) * (self::W / CardGenerationService::EDITOR_WIDTH) * 0.75;

        $text = ArabicText::forRendering($text);
        $hex = ltrim((string) ($part['color'] ?? '#000000'), '#');
        $color = imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );

        $bbox = imagettfbbox($size, 0, $font, $text);
        $tw = abs($bbox[4] - $bbox[0]);
        if ($tw > $w && $tw > 0) {
            $size *= $w / $tw;
            $bbox = imagettfbbox($size, 0, $font, $text);
            $tw = abs($bbox[4] - $bbox[0]);
        }
        $th = abs($bbox[5] - $bbox[1]);

        $left = match ($part['direction'] ?? 'center') {
            'ltr' => $x,
            'rtl' => $x + $w - $tw,
            default => $x + ($w - $tw) / 2,
        };

        imagettftext($image, $size, 0, (int) $left, (int) ($y + ($h + $th) / 2), $color, $font, $text);
    }

    /** QR on a white rounded-feeling square so it scans on any artwork. */
    private function drawQr(\GdImage $image, string $value, int $x, int $y, int $side): void
    {
        $pad = (int) ($side * 0.08);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, $x, $y, $x + $side - 1, $y + $side - 1, $white);

        $inner = $side - $pad * 2;
        $result = (new Builder)->build(
            new PngWriter, null, null, $value, new Encoding('UTF-8'),
            ErrorCorrectionLevel::Medium, $inner, 0, RoundBlockSizeMode::Margin,
            new Color(0, 0, 0), new Color(255, 255, 255),
        );
        $qr = imagecreatefromstring($result->getString());
        if ($qr) {
            imagecopyresampled($image, $qr, $x + $pad, $y + $pad, 0, 0, $inner, $inner, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
    }
}
