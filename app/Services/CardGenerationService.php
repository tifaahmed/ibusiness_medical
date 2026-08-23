<?php

namespace App\Services;

use App\Enums\CardTemplate\CardTemplateStatusEnum;
use App\Models\CardLayout;
use App\Models\CardTemplate;
use App\Models\Membership;
use App\Support\ArabicText;
use App\Support\CardTemplateLayoutDefaults;
use App\Support\PublicMembershipUrl;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class CardGenerationService
{
    /** Export width in px. Height follows the artwork's own aspect ratio. */
    public const CARD_W = 1579;

    /** Must match CardTemplateLayoutDefaults::EDITOR_WIDTH. */
    public const EDITOR_WIDTH = 700;

    public const INK = '#000000';

    public const QR_FRAME_COLOR = '#0a1128';

    /** Fraction of the QR box left white around the modules. */
    public const QR_QUIET_ZONE = 22 / 281;

    public const QR_CORNER_RADIUS = 18 / 281;

    /** Code128 bar patterns — same array as code128.js. */
    private const CODE128_PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    private const CODE128_START_B = 104;

    private const CODE128_START_C = 105;

    private const CODE128_CODE_C = 99;

    private const CODE128_STOP = 106;

    private const OVERRIDE_TARGETS = [
        'qr' => ['qrcode'],
        'barcode' => ['barcode'],
        'partner' => ['partner_logo'],
        'contact' => ['facebook', 'website', 'phone'],
    ];

    public function generate(Membership $membership, string $mode = 'full'): ?string
    {
        $layout = $membership->cardLayouts()->where('mode', $mode)->first();

        $cardTemplate = $layout && $layout->relationLoaded('cardTemplate') ? $layout->cardTemplate : null;
        if (! $cardTemplate) {
            $cardTemplate = $layout?->cardTemplate;
        }

        // When no template is linked to the layout, pick the right one based
        // on partner status — matching the JS buildCardTemplate() logic.
        if (! $cardTemplate) {
            $status = $membership->partner_id
                ? CardTemplateStatusEnum::WITH_PARTNER
                : CardTemplateStatusEnum::NO_PARTNER;
            $cardTemplate = CardTemplate::where('status', $status)->first()
                ?? CardTemplate::first();
        }

        $templatePath = null;
        if ($cardTemplate && $cardTemplate->card_empty) {
            $candidate = public_path(ltrim($cardTemplate->card_empty, '/'));
            if (file_exists($candidate)) {
                $templatePath = $candidate;
            }
        }

        if (! $templatePath) {
            $templatePath = $mode === 'minimal'
                ? public_path('card-template_white.jpg')
                : public_path('images/cards/deilar-card-blank.png');
        }

        if (! file_exists($templatePath)) {
            return null;
        }

        $image = match (strtolower(pathinfo($templatePath, PATHINFO_EXTENSION))) {
            'png' => @imagecreatefrompng($templatePath),
            'webp' => @imagecreatefromwebp($templatePath),
            default => @imagecreatefromjpeg($templatePath),
        };
        if (! $image) {
            return null;
        }
        imagesavealpha($image, true);
        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);

        // Use the card_layout's own layout when one was saved, falling back
        // to the template's default — matching the JS buildCardTemplate().
        $tplLayout = $cardTemplate ? $cardTemplate->effectiveLayout() : CardTemplateLayoutDefaults::layout();
        if ($layout && ! empty($layout->layout)) {
            $tplLayout = $layout->layout;
        }

        // Resolve the template's fractional layout into absolute pixel boxes,
        // matching the JS resolveFields() in cardRenderer.js.
        $hiddenFields = array_flip($cardTemplate ? ($cardTemplate->hidden_fields ?? []) : []);
        $fields = $this->resolveFields($tplLayout, $hiddenFields, $width, $height);

        // Merge sample_data (template-fixed values) with per-member values.
        $sampleData = $cardTemplate ? ($cardTemplate->sample_data ?? []) : [];
        $sampleData = array_merge(CardTemplateLayoutDefaults::sampleData(), $sampleData);

        // Use the card_layout's own field_values when saved (customised card),
        // matching the JS renderCardCanvas() valueOverrides logic.
        $fieldValues = $layout && ! empty($layout->field_values) ? $layout->field_values : [];

        $values = $sampleData;
        $values['membership_number'] = (string) $membership->membership_number;
        $values['barcode'] = (string) ($membership->membership_number ?? '');
        $values['qrcode'] = PublicMembershipUrl::qrForSlug($membership->slug);

        // Apply per-card field value overrides (non-empty only).
        foreach ($fieldValues as $fk => $fv) {
            if ($fv !== '' && $fv !== null) {
                $values[$fk] = $fv;
            }
        }

        // Partner logo: the partner's own image wins over the template placeholder.
        $partner = $membership->partner;
        if ($partner && $partner->image) {
            $values['partner_logo'] = $partner->image;
        }

        // Apply per-card position overrides only when no custom layout is
        // stored — once a card is saved through the generator the full layout
        // is in `$layout->layout` and individual overrides are redundant.
        if (empty($layout->layout)) {
            $overrides = $this->buildOverrides($layout);
            if ($overrides) {
                $fields = $this->applyOverrides($fields, $overrides, $width, $height);
            }
        }

        // 1. Draw image fields first (logo, partner_logo).
        foreach (['logo', 'partner_logo'] as $key) {
            if (! isset($fields[$key]) || empty($values[$key])) {
                continue;
            }
            $this->drawImageField($image, $fields[$key], $values[$key], $width, $height);
        }

        // 2. Draw QR code with rounded white frame.
        $this->drawQrCodeField($image, $fields, $values, $width);

        // 3. Draw barcode.
        $this->drawBarcodeField($image, $fields, $values, $width);

        // 4. Draw text fields (slogan, membership_number, facebook, website, phone).
        foreach (['slogan', 'membership_number', 'facebook', 'website', 'phone'] as $key) {
            if (! isset($fields[$key]) || empty($values[$key])) {
                continue;
            }
            $this->drawTextField($image, $fields[$key], (string) $values[$key], $width);
        }

        $filename = 'cards/card-'.$membership->id.'-'.time().'.png';
        $storagePath = Storage::disk('public')->path($filename);
        $dir = dirname($storagePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        imagepng($image, $storagePath);
        imagedestroy($image);

        if ($layout) {
            $layout->update(['generated_image_path' => $filename]);
        } else {
            $membership->cardLayouts()->create([
                'mode' => $mode,
                'generated_image_path' => $filename,
            ]);
        }

        return Storage::disk('public')->url($filename);
    }

    /**
     * Resolve a template's fractional layout into absolute pixel boxes,
     * matching the JS resolveFields() in cardRenderer.js.
     *
     * @param  array<string, array<string, mixed>>  $tplLayout
     * @param  array<int, string>  $hiddenFields  flipped array of hidden field keys
     * @return array<string, array{left: float, top: float, width: float, height: float, fontSize: float, color: string, direction: string}>
     */
    private function resolveFields(array $tplLayout, array $hiddenFields, int $width, int $height): array
    {
        $fields = [];
        foreach ($tplLayout as $key => $box) {
            if (isset($hiddenFields[$key]) || ! is_array($box)) {
                continue;
            }
            $fields[$key] = [
                'left' => (float) ($box['x'] ?? 0) * $width,
                'top' => (float) ($box['y'] ?? 0) * $height,
                'width' => (float) ($box['width'] ?? 0) * $width,
                'height' => (float) ($box['height'] ?? 0) * $height,
                'fontSize' => ((float) ($box['font_size'] ?? 14)) * ($width / self::EDITOR_WIDTH),
                'color' => (string) ($box['color'] ?? self::INK),
                'direction' => (string) ($box['direction'] ?? 'ltr'),
            ];
        }

        return $fields;
    }

    /**
     * Build per-batch overrides from a card_layout row's position fields,
     * matching the JS getCardOverrides() in Show.vue.
     */
    private function buildOverrides(?CardLayout $layout): ?array
    {
        if (! $layout) {
            return null;
        }

        $overrides = [];

        if (! empty($layout->qr_x) || ! empty($layout->qr_y)) {
            $overrides['qr'] = [
                'x' => (float) ($layout->qr_x ?? 0),
                'y' => (float) ($layout->qr_y ?? 0),
                'scale' => (float) ($layout->qr_scale ?? 1),
            ];
        }

        if (! empty($layout->fields_x) || ! empty($layout->fields_y)) {
            $overrides['contact'] = [
                'x' => (float) ($layout->fields_x ?? 0),
                'y' => (float) ($layout->fields_y ?? 0),
                'scale' => (float) ($layout->fields_scale ?? 1),
            ];
        }

        if (! empty($layout->partner_x) || ! empty($layout->partner_y)) {
            $overrides['partner'] = [
                'x' => (float) ($layout->partner_x ?? 0),
                'y' => (float) ($layout->partner_y ?? 0),
                'scale' => (float) ($layout->partner_scale ?? 1),
            ];
        }

        return $overrides ?: null;
    }

    /**
     * Apply per-batch overrides on top of the resolved fields, matching the
     * JS resolveFields() override logic in cardRenderer.js.
     *
     * The contact override moves facebook/website/phone as a group; qr and
     * partner each move a single field.
     */
    private function applyOverrides(array $fields, array $overrides, int $width, int $height): array
    {
        foreach (self::OVERRIDE_TARGETS as $overrideKey => $targetKeys) {
            $ov = $overrides[$overrideKey] ?? null;
            if (! $ov) {
                continue;
            }

            $scale = isset($ov['scale']) && is_numeric($ov['scale']) ? (float) $ov['scale'] : 1.0;
            $x = isset($ov['x']) ? (float) $ov['x'] : null;
            $y = isset($ov['y']) ? (float) $ov['y'] : null;

            // The anchor is the first target field — dx/dy shift the whole
            // group from that anchor's resolved position.
            $anchorKey = $targetKeys[0];
            $anchor = $fields[$anchorKey] ?? null;
            $dx = ($x !== null && $anchor) ? $x - $anchor['left'] : 0;
            $dy = ($y !== null && $anchor) ? $y - $anchor['top'] : 0;

            foreach ($targetKeys as $key) {
                if (! isset($fields[$key])) {
                    continue;
                }
                $f = &$fields[$key];
                $f['left'] += $dx;
                $f['top'] += $dy;
                $f['width'] *= $scale;
                $f['height'] *= $scale;
                $f['fontSize'] *= $scale;
                unset($f);
            }
        }

        return $fields;
    }

    /**
     * Contain-fit an image inside the field box, centered — matches the JS
     * drawImageField().
     */
    private function drawImageField(\GdImage $image, array $field, string $srcPath, int $cardW, int $cardH): void
    {
        $resolved = $this->resolveAssetPath($srcPath);
        if (! $resolved || ! file_exists($resolved)) {
            return;
        }

        $src = $this->createImageFromFile($resolved);
        if (! $src) {
            return;
        }

        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($srcW <= 0 || $srcH <= 0) {
            imagedestroy($src);

            return;
        }

        $fit = min($field['width'] / $srcW, $field['height'] / $srcH);
        $dw = (int) ($srcW * $fit);
        $dh = (int) ($srcH * $fit);
        $dx = (int) ($field['left'] + ($field['width'] - $dw) / 2);
        $dy = (int) ($field['top'] + ($field['height'] - $dh) / 2);

        $resized = imagecreatetruecolor($dw, $dh);
        imagesavealpha($resized, true);
        imagealphablending($resized, false);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $dw, $dh, $srcW, $srcH);

        imagealphablending($image, true);
        imagecopy($image, $resized, $dx, $dy, 0, 0, $dw, $dh);

        imagedestroy($src);
        imagedestroy($resized);
    }

    /**
     * Draw the QR code inside a white rounded-rect frame with a dark border,
     * matching the JS rendering in cardRenderer.js.
     */
    private function drawQrCodeField(\GdImage $image, array $fields, array $values, int $cardWidth): void
    {
        $qr = $fields['qrcode'] ?? null;
        if (! $qr || empty($values['qrcode'])) {
            return;
        }

        $box = min($qr['width'], $qr['height']);
        $inset = $box * self::QR_QUIET_ZONE;
        $cornerRadius = $box * self::QR_CORNER_RADIUS;
        $borderWidth = max(1, (int) (5 / self::CARD_W * $cardWidth));

        // Generate the QR code.
        $qrSize = max(64, (int) ($box - $inset * 2));
        try {
            $builder = new Builder;
            $result = $builder->build(
                new PngWriter,
                null,
                null,
                $values['qrcode'],
                new Encoding('UTF-8'),
                ErrorCorrectionLevel::Medium,
                $qrSize,
                0,
                RoundBlockSizeMode::Margin,
                new Color(0, 0, 0),
                new Color(255, 255, 255),
            );
            $qrTempPath = tempnam(sys_get_temp_dir(), 'qr_');
            file_put_contents($qrTempPath, $result->getString());
            $qrImage = imagecreatefrompng($qrTempPath);
            @unlink($qrTempPath);
        } catch (\Exception $e) {
            return;
        }

        if (! $qrImage) {
            return;
        }

        // Draw the white rounded-rect frame.
        $this->drawRoundedRect(
            $image,
            (int) $qr['left'],
            (int) $qr['top'],
            (int) $qr['width'],
            (int) $qr['height'],
            (int) $cornerRadius,
            [255, 255, 255],
        );

        // Draw the dark border.
        $borderColor = $this->hexToRgb(self::QR_FRAME_COLOR);
        imagefilledrectangle(
            $image,
            (int) $qr['left'],
            (int) $qr['top'],
            (int) ($qr['left'] + $qr['width'] - 1),
            (int) ($qr['top'] + $borderWidth - 1),
            imagecolorallocate($image, $borderColor[0], $borderColor[1], $borderColor[2]),
        );
        imagefilledrectangle(
            $image,
            (int) $qr['left'],
            (int) ($qr['top'] + $qr['height'] - $borderWidth),
            (int) ($qr['left'] + $qr['width'] - 1),
            (int) ($qr['top'] + $qr['height'] - 1),
            imagecolorallocate($image, $borderColor[0], $borderColor[1], $borderColor[2]),
        );
        imagefilledrectangle(
            $image,
            (int) $qr['left'],
            (int) $qr['top'],
            (int) ($qr['left'] + $borderWidth - 1),
            (int) ($qr['top'] + $qr['height'] - 1),
            imagecolorallocate($image, $borderColor[0], $borderColor[1], $borderColor[2]),
        );
        imagefilledrectangle(
            $image,
            (int) ($qr['left'] + $qr['width'] - $borderWidth),
            (int) $qr['top'],
            (int) ($qr['left'] + $qr['width'] - 1),
            (int) ($qr['top'] + $qr['height'] - 1),
            imagecolorallocate($image, $borderColor[0], $borderColor[1], $borderColor[2]),
        );

        // Draw the QR code inside the frame.
        $qrW = imagesx($qrImage);
        $qrH = imagesy($qrImage);
        $destW = max(1, (int) ($qr['width'] - $inset * 2));
        $destH = max(1, (int) ($qr['height'] - $inset * 2));
        $resized = imagecreatetruecolor($destW, $destH);
        imagesavealpha($resized, true);
        imagealphablending($resized, false);
        imagecopyresampled($resized, $qrImage, 0, 0, 0, 0, $destW, $destH, $qrW, $qrH);

        imagealphablending($image, true);
        imagecopy($image, $resized, (int) ($qr['left'] + $inset), (int) ($qr['top'] + $inset), 0, 0, $destW, $destH);

        imagedestroy($qrImage);
        imagedestroy($resized);
    }

    /**
     * Draw a Code128 barcode stretched to fill the field box.
     */
    private function drawBarcodeField(\GdImage $image, array $fields, array $values, int $cardWidth): void
    {
        $bars = $fields['barcode'] ?? null;
        if (! $bars || empty($values['barcode'])) {
            return;
        }

        $encoded = $this->encodeCode128((string) $values['barcode']);
        if ($encoded['modules'] <= 0) {
            return;
        }

        $unit = $bars['width'] / $encoded['modules'];
        $ink = $this->hexToRgb(self::INK);
        $inkColor = imagecolorallocate($image, $ink[0], $ink[1], $ink[2]);

        foreach ($encoded['bars'] as $bar) {
            $x1 = (int) ($bars['left'] + $bar['offset'] * $unit);
            $x2 = (int) ($bars['left'] + ($bar['offset'] + $bar['width']) * $unit);
            imagefilledrectangle($image, $x1, (int) $bars['top'], $x2 - 1, (int) ($bars['top'] + $bars['height']), $inkColor);
        }
    }

    /**
     * Draw a text field inside its box, matching the JS drawTextField().
     * Auto-shrinks the font when text exceeds the box width.
     */
    private function drawTextField(\GdImage $image, array $field, string $text, int $cardWidth): void
    {
        if ($text === '') {
            return;
        }

        $fontPath = $this->resolveFontPath('Tajawal-Bold.ttf');
        if (! $fontPath) {
            return;
        }

        // GD neither shapes nor reorders Arabic, so hand it the visual-order
        // string the browser's canvas would have produced for the live preview.
        $text = ArabicText::forRendering($text);

        $fontSize = $field['fontSize'];
        $color = $this->hexToRgb($field['color']);
        $textColor = imagecolorallocate($image, $color[0], $color[1], $color[2]);

        // Auto-shrink: if the text is wider than the box, scale down.
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textW = abs($bbox[4] - $bbox[0]);
        if ($textW > $field['width'] && $textW > 0) {
            $fontSize *= $field['width'] / $textW;
        }

        // Re-measure with the final size.
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textW = abs($bbox[4] - $bbox[0]);
        $textH = abs($bbox[5] - $bbox[1]);

        $direction = $field['direction'] ?? 'ltr';
        if ($direction === 'center') {
            $x = (int) ($field['left'] + ($field['width'] - $textW) / 2);
        } elseif ($direction === 'rtl') {
            $x = (int) ($field['left'] + $field['width'] - $textW);
        } else {
            $x = (int) $field['left'];
        }

        // Vertically center in the box.
        $y = (int) ($field['top'] + ($field['height'] + $textH) / 2);

        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $fontPath, $text);
    }

    /**
     * Encode a string as Code 128 (subset B, or C for purely numeric payloads),
     * ported from code128.js.
     *
     * @return array{bars: array<array{offset: float, width: float}>, modules: float}
     */
    private function encodeCode128(string $value): array
    {
        if (! preg_match('/^\d+$/', $value)) {
            $start = self::CODE128_START_B;
            $data = [];
            for ($i = 0; $i < strlen($value); $i++) {
                $code = ord($value[$i]);
                $data[] = ($code >= 32 && $code <= 126) ? $code - 32 : 0;
            }
        } elseif (strlen($value) % 2 === 0) {
            $start = self::CODE128_START_C;
            $data = [];
            for ($i = 0; $i < strlen($value); $i += 2) {
                $data[] = (int) substr($value, $i, 2);
            }
        } else {
            $data = [ord($value[0]) - 32, self::CODE128_CODE_C];
            for ($i = 1; $i < strlen($value); $i += 2) {
                $data[] = (int) substr($value, $i, 2);
            }
            $start = self::CODE128_START_B;
        }

        $checksum = $start;
        foreach ($data as $i => $v) {
            $checksum += $v * ($i + 1);
        }

        $codes = array_merge([$start], $data, [$checksum % 103, self::CODE128_STOP]);

        $bars = [];
        $offset = 0;
        foreach ($codes as $code) {
            $pattern = self::CODE128_PATTERNS[$code] ?? '';
            for ($i = 0; $i < strlen($pattern); $i++) {
                $width = (float) $pattern[$i];
                if ($i % 2 === 0) {
                    $bars[] = ['offset' => $offset, 'width' => $width];
                }
                $offset += $width;
            }
        }

        return ['bars' => $bars, 'modules' => $offset];
    }

    /**
     * Draw a filled rounded rectangle on the image.
     */
    private function drawRoundedRect(\GdImage $image, int $x, int $y, int $w, int $h, int $radius, array $rgb): void
    {
        $radius = min($radius, (int) ($w / 2), (int) ($h / 2));
        $color = imagecolorallocate($image, $rgb[0], $rgb[1], $rgb[2]);

        // Fill body.
        imagefilledrectangle($image, $x + $radius, $y, $x + $w - $radius - 1, $y + $h - 1, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $w - 1, $y + $h - $radius - 1, $color);

        // Four corners.
        imagefilledrectangle($image, $x, $y, $x + $radius - 1, $y + $radius - 1, $color);
        imagefilledrectangle($image, $x + $w - $radius, $y, $x + $w - 1, $y + $radius - 1, $color);
        imagefilledrectangle($image, $x, $y + $h - $radius, $x + $radius - 1, $y + $h - 1, $color);
        imagefilledrectangle($image, $x + $w - $radius, $y + $h - $radius, $x + $w - 1, $y + $h - 1, $color);

        // Smooth corners with circles.
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $cornerCenters = [
            [$x + $radius, $y + $radius],
            [$x + $w - $radius - 1, $y + $radius],
            [$x + $radius, $y + $h - $radius - 1],
            [$x + $w - $radius - 1, $y + $h - $radius - 1],
        ];
        foreach ($cornerCenters as [$cx, $cy]) {
            for ($px = $cx - $radius; $px <= $cx + $radius; $px++) {
                for ($py = $cy - $radius; $py <= $cy + $radius; $py++) {
                    if ($px < $x || $px >= $x + $w || $py < $y || $py >= $y + $h) {
                        continue;
                    }
                    $dist = sqrt(($px - $cx) ** 2 + ($py - $cy) ** 2);
                    if ($dist > $radius) {
                        imagesetpixel($image, $px, $py, $transparent);
                    }
                }
            }
        }
    }

    private function resolveAssetPath(string $src): ?string
    {
        if ($src === '' || $src === null) {
            return null;
        }

        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            $path = parse_url($src, PHP_URL_PATH);
            if ($path) {
                $src = $path;
            }
        }

        $src = ltrim($src, '/');

        $local = public_path($src);
        if (file_exists($local)) {
            return $local;
        }

        $storage = Storage::disk('public')->path($src);
        if (file_exists($storage)) {
            return $storage;
        }

        return null;
    }

    private function createImageFromFile(string $path): ?\GdImage
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            default => @imagecreatefromjpeg($path),
        };
    }

    private function resolveFontPath(string $filename): ?string
    {
        $paths = [
            storage_path('app/public/fonts/'.$filename),
            public_path('fonts/'.$filename),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Generate (or serve cached) the back side of a membership card.
     *
     * The back is a static artwork — no fields are drawn on it — so this
     * simply copies the shipped artwork into storage on first call and
     * reuses the path thereafter.
     */
    public function generateBack(Membership $membership, string $mode = 'full'): ?string
    {
        $layout = $membership->cardLayouts()->where('mode', $mode)->first();

        if ($layout && ! empty($layout->generated_back_image_path)) {
            $path = Storage::disk('public')->path($layout->generated_back_image_path);
            if (file_exists($path)) {
                return Storage::disk('public')->url($layout->generated_back_image_path);
            }
        }

        $backTemplate = public_path('images/cards/card-backside.png');
        if (! file_exists($backTemplate)) {
            return null;
        }

        $filename = 'cards/card-'.$membership->id.'-back-'.time().'.png';
        $destPath = Storage::disk('public')->path($filename);
        $dir = dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! copy($backTemplate, $destPath)) {
            return null;
        }

        if ($layout) {
            $layout->update(['generated_back_image_path' => $filename]);
        } else {
            $membership->cardLayouts()->create([
                'mode' => $mode,
                'generated_back_image_path' => $filename,
            ]);
        }

        return Storage::disk('public')->url($filename);
    }
}
