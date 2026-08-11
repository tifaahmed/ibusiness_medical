<?php

namespace App\Services;

use App\Models\CardLayout;
use App\Models\Membership;
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
    const CW = 1063;

    const CH = 650;

    const DEFAULT_FULL = [
        'name' => ['x' => 796, 'y' => 337, 'scale' => 1],
        'photo' => ['x' => 690, 'y' => 94,  'scale' => 1],
        'fields' => ['x' => 572, 'y' => 413, 'scale' => 1],
        'qr' => ['x' => 376, 'y' => 410, 'scale' => 0.65],
        'partner' => ['x' => -87, 'y' => 361, 'scale' => 2.65],
    ];

    const DEFAULT_FULL_EMPTY = [
        'name' => ['x' => 796, 'y' => 337, 'scale' => 1],
        'photo' => ['x' => 690, 'y' => 94,  'scale' => 1],
        'fields' => ['x' => 779, 'y' => 442, 'scale' => 1.1],
        'qr' => ['x' => 376, 'y' => 410, 'scale' => 0.65],
        'partner' => ['x' => 95,  'y' => 405, 'scale' => 1.16],
    ];

    const DEFAULT_MINIMAL = [
        'qr' => ['x' => 704, 'y' => 107, 'scale' => 0.6],
        'fields' => ['x' => 756, 'y' => 368, 'scale' => 1.2],
        'partner' => ['x' => -165, 'y' => 313, 'scale' => 2.65],
    ];

    public function generate(Membership $membership, string $mode = 'full'): ?string
    {
        $layout = $membership->cardLayouts()->where('mode', $mode)->first();
        $templatePath = $mode === 'minimal'
            ? public_path('card-template_white.jpg')
            : public_path('card-template_pure.jpg');

        if (! file_exists($templatePath)) {
            return null;
        }

        $image = imagecreatefromjpeg($templatePath);
        imagesavealpha($image, true);

        if ($mode === 'full') {
            $this->drawPartnerLogo($image, $membership, $layout, self::DEFAULT_FULL);
            $this->drawMemberPhoto($image, $membership, $layout);
            $this->drawName($image, $membership, $layout, $mode);
            $this->drawFields($image, $membership, $layout, $mode);
            $this->drawQrCode($image, $membership, $layout, self::DEFAULT_FULL);
        } else {
            $this->drawPartnerLogo($image, $membership, $layout, self::DEFAULT_MINIMAL);
            $this->drawFields($image, $membership, $layout, $mode);
            $this->drawQrCode($image, $membership, $layout, self::DEFAULT_MINIMAL);
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

    protected function drawPartnerLogo($image, Membership $membership, ?CardLayout $layout, array $defaults): void
    {
        $partner = $membership->partner;
        if (! $partner || ! $partner->image) {
            return;
        }

        $partnerImagePath = public_path(ltrim(parse_url($partner->image, PHP_URL_PATH) ?: $partner->image, '/'));
        if (! file_exists($partnerImagePath)) {
            return;
        }

        $partnerImg = null;
        $info = getimagesize($partnerImagePath);
        if (! $info) {
            return;
        }

        $ext = strtolower(pathinfo($partnerImagePath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $partnerImg = imagecreatefrompng($partnerImagePath);
        } elseif (in_array($ext, ['jpg', 'jpeg'])) {
            $partnerImg = imagecreatefromjpeg($partnerImagePath);
        } elseif ($ext === 'webp') {
            $partnerImg = imagecreatefromwebp($partnerImagePath);
        }

        if (! $partnerImg) {
            return;
        }

        $key = 'partner';
        $l = $layout ? [
            'x' => $layout->partner_x ?? $defaults[$key]['x'],
            'y' => $layout->partner_y ?? $defaults[$key]['y'],
            'scale' => $layout->partner_scale ?? $defaults[$key]['scale'],
        ] : $defaults[$key];

        $maxW = (int) (170 * $l['scale']);
        $maxH = (int) (170 * $l['scale']);
        $srcW = imagesx($partnerImg);
        $srcH = imagesy($partnerImg);
        $ar = $srcW / $srcH;
        if ($ar > 1) {
            $dw = $maxW;
            $dh = (int) ($dw / $ar);
        } else {
            $dh = $maxH;
            $dw = (int) ($dh * $ar);
        }

        $resized = imagecreatetruecolor($dw, $dh);
        imagesavealpha($resized, true);
        imagealphablending($resized, false);
        imagecopyresampled($resized, $partnerImg, 0, 0, 0, 0, $dw, $dh, $srcW, $srcH);
        imagecopy($image, $resized, (int) $l['x'], (int) $l['y'], 0, 0, $dw, $dh);

        imagedestroy($partnerImg);
        imagedestroy($resized);
    }

    protected function drawMemberPhoto($image, Membership $membership, ?CardLayout $layout): void
    {
        $user = $membership->user;
        if (! $user || ! $user->avatar_url) {
            return;
        }

        $photoUrl = $user->avatar_url;
        $photoContent = @file_get_contents($photoUrl);
        if (! $photoContent) {
            return;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'photo_');
        file_put_contents($tempPath, $photoContent);
        $photoImg = imagecreatefromstring($photoContent);
        if (! $photoImg) {
            @unlink($tempPath);

            return;
        }

        $key = 'photo';
        $defaults = self::DEFAULT_FULL;
        $l = $layout ? [
            'x' => $layout->photo_x ?? $defaults[$key]['x'],
            'y' => $layout->photo_y ?? $defaults[$key]['y'],
            'scale' => $layout->photo_scale ?? $defaults[$key]['scale'],
        ] : $defaults[$key];

        $pw = (int) (178 * $l['scale']);
        $ph = (int) (178 * $l['scale']);
        $px = (int) $l['x'];
        $py = (int) $l['y'];

        $srcW = imagesx($photoImg);
        $srcH = imagesy($photoImg);
        $ar = $srcW / $srcH;
        $tar = $pw / $ph;
        if ($ar > $tar) {
            $sw = (int) ($srcH * $tar);
            $sh = $srcH;
            $sx = (int) (($srcW - $sw) / 2);
            $sy = 0;
        } else {
            $sw = $srcW;
            $sh = (int) ($srcW / $tar);
            $sx = 0;
            $sy = (int) (($srcH - $sh) / 2);
        }

        $resized = imagecreatetruecolor($pw, $ph);
        imagesavealpha($resized, true);
        imagealphablending($resized, false);
        imagecopyresampled($resized, $photoImg, 0, 0, $sx, $sy, $pw, $ph, $sw, $sh);

        $radius = (int) (10 * ($l['scale'] ?? 1));
        $this->applyRoundedCorners($resized, $pw, $ph, $radius);
        imagecopy($image, $resized, $px, $py, 0, 0, $pw, $ph);

        imagedestroy($photoImg);
        imagedestroy($resized);
        @unlink($tempPath);
    }

    protected function drawName($image, Membership $membership, ?CardLayout $layout, string $mode): void
    {
        $user = $membership->user;
        if (! $user || ! $user->name) {
            return;
        }

        $defaults = $mode === 'full' ? self::DEFAULT_FULL : [];
        $key = 'name';
        $l = $layout ? [
            'x' => $layout->name_x ?? $defaults[$key]['x'],
            'y' => $layout->name_y ?? $defaults[$key]['y'],
            'scale' => $layout->name_scale ?? $defaults[$key]['scale'],
        ] : $defaults[$key];

        $color = $layout->name_color ?? '#000000';
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $textColor = imagecolorallocate($image, $r, $g, $b);

        $fontPath = storage_path('app/public/fonts/Tajawal-Bold.ttf');
        if (! file_exists($fontPath)) {
            $fontPath = null;
        }

        $emptyMode = ! $membership->job_title && ! $membership->company_name;
        $fontSize = (int) (($emptyMode ? 46 : 34) * $l['scale']);

        if ($fontPath) {
            /*
             * Only measurable with a font in hand — imagettfbbox() is fatal on
             * a null path, and the bundled Tajawal is missing on installs where
             * storage/app/public has never been populated. The fallback below
             * measures the built-in font its own way.
             */
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $user->name);
            $textW = $bbox[2] - $bbox[0];
            $textX = (int) $l['x'] - (int) ($textW / 2);

            imagettftext($image, $fontSize, 0, $textX, (int) $l['y'], $textColor, $fontPath, $user->name);
        } else {
            $fontSize = max(1, (int) ($fontSize / 8));
            $textX = (int) $l['x'] - (int) (strlen($user->name) * $fontSize * 0.6 / 2);
            imagestring($image, $fontSize, $textX, (int) $l['y'] - 10, $user->name, $textColor);
        }
    }

    protected function drawFields($image, Membership $membership, ?CardLayout $layout, string $mode): void
    {
        $defaults = $mode === 'minimal' ? self::DEFAULT_MINIMAL : self::DEFAULT_FULL;
        $key = 'fields';
        $l = $layout ? [
            'x' => $layout->fields_x ?? $defaults[$key]['x'],
            'y' => $layout->fields_y ?? $defaults[$key]['y'],
            'scale' => $layout->fields_scale ?? $defaults[$key]['scale'],
        ] : $defaults[$key];

        $color = $layout->fields_color ?? '#000000';
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $textColor = imagecolorallocate($image, $r, $g, $b);

        $fontPath = storage_path('app/public/fonts/Tajawal-Regular.ttf');
        if (! file_exists($fontPath)) {
            $fontPath = null;
        }

        $policy = $membership->membership_number;
        $member = $membership->getTranslation('job_title', app()->getLocale()) ?: $membership->getTranslation('job_title', 'ar');
        $status = $membership->company_id
            ? ($membership->company?->getTranslation('name', app()->getLocale()) ?: $membership->company?->getTranslation('name', 'ar'))
            : null;
        $valid = $membership->expiration_date?->format('F j, Y');

        if ($mode === 'minimal') {
            if (! $policy) {
                return;
            }
            $fSize = (int) (34 * $l['scale']);
            if ($fontPath) {
                $bbox = imagettfbbox($fSize, 0, $fontPath, (string) $policy);
                $textW = $bbox[2] - $bbox[0];
                $textX = (int) $l['x'] - (int) ($textW / 2);
                imagettftext($image, $fSize, 0, $textX, (int) $l['y'], $textColor, $fontPath, (string) $policy);
            } else {
                $fs = max(1, (int) ($fSize / 8));
                $textX = (int) $l['x'] - (int) (strlen($policy) * $fs * 0.6 / 2);
                imagestring($image, $fs, $textX, (int) $l['y'] - 10, (string) $policy, $textColor);
            }

            return;
        }

        $emptyMode = ! $member && ! $status;
        $items = $emptyMode
            ? array_filter([
                'Policy no / '.$policy => $policy,
                'Valid to / '.$valid => $valid,
            ], fn ($v) => (bool) $v)
            : array_filter([
                'Policy no / '.$policy => $policy,
                'Member / '.$member => $member,
                'Status / '.$status => $status,
                'Valid to / '.$valid => $valid,
            ], fn ($v) => (bool) $v);

        $fSize = $emptyMode ? (int) (34 * $l['scale']) : (int) (32 * $l['scale']);
        $lineH = $emptyMode ? (int) (75 * $l['scale']) : (int) (47 * $l['scale']);

        $i = 0;
        foreach ($items as $text => $val) {
            $y = (int) $l['y'] + $i * $lineH;
            if ($fontPath) {
                if ($emptyMode) {
                    $bbox = imagettfbbox($fSize, 0, $fontPath, $text);
                    $textW = $bbox[2] - $bbox[0];
                    $textX = (int) $l['x'] - (int) ($textW / 2);
                    imagettftext($image, $fSize, 0, $textX, $y, $textColor, $fontPath, $text);
                } else {
                    imagettftext($image, $fSize, 0, (int) $l['x'], $y, $textColor, $fontPath, $text);
                }
            } else {
                $fs = max(1, (int) ($fSize / 8));
                $textX = $emptyMode ? ((int) $l['x'] - (int) (strlen($text) * $fs * 0.6 / 2)) : (int) $l['x'];
                imagestring($image, $fs, $textX, $y - 10, $text, $textColor);
            }
            $i++;
        }
    }

    protected function drawQrCode($image, Membership $membership, ?CardLayout $layout, array $defaults): void
    {
        $key = 'qr';
        $l = $layout ? [
            'x' => $layout->qr_x ?? $defaults[$key]['x'],
            'y' => $layout->qr_y ?? $defaults[$key]['y'],
            'scale' => $layout->qr_scale ?? $defaults[$key]['scale'],
        ] : $defaults[$key];

        $qrUrl = PublicMembershipUrl::forSlug($membership->slug);
        $qrSize = (int) (190 * $l['scale']);
        $pad = (int) (14 * $l['scale']);

        try {
            $builder = new Builder;
            $result = $builder->build(
                new PngWriter,
                null,
                null,
                $qrUrl,
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
            if ($qrImage) {
                $bgColor = imagecolorallocate($image, 255, 255, 255);
                imagefilledrectangle(
                    $image,
                    (int) $l['x'] - $pad,
                    (int) $l['y'] - $pad,
                    (int) $l['x'] + $qrSize + $pad,
                    (int) $l['y'] + $qrSize + $pad,
                    $bgColor
                );
                imagecopy($image, $qrImage, (int) $l['x'], (int) $l['y'], 0, 0, $qrSize, $qrSize);
                imagedestroy($qrImage);
            }

            @unlink($qrTempPath);
        } catch (\Exception $e) {
        }
    }

    protected function applyRoundedCorners($image, int $w, int $h, int $radius): void
    {
        $black = imagecolorallocatealpha($image, 0, 0, 0, 127);
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $isCorner = false;
                if ($x < $radius && $y < $radius) {
                    $dist = sqrt(($radius - $x) ** 2 + ($radius - $y) ** 2);
                    if ($dist > $radius) {
                        $isCorner = true;
                    }
                } elseif ($x >= $w - $radius && $y < $radius) {
                    $dist = sqrt(($radius - ($w - 1 - $x)) ** 2 + ($radius - $y) ** 2);
                    if ($dist > $radius) {
                        $isCorner = true;
                    }
                } elseif ($x < $radius && $y >= $h - $radius) {
                    $dist = sqrt(($radius - $x) ** 2 + ($radius - ($h - 1 - $y)) ** 2);
                    if ($dist > $radius) {
                        $isCorner = true;
                    }
                } elseif ($x >= $w - $radius && $y >= $h - $radius) {
                    $dist = sqrt(($radius - ($w - 1 - $x)) ** 2 + ($radius - ($h - 1 - $y)) ** 2);
                    if ($dist > $radius) {
                        $isCorner = true;
                    }
                }
                if ($isCorner) {
                    imagesetpixel($image, $x, $y, $black);
                }
            }
        }
    }
}
