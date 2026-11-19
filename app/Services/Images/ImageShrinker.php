<?php

namespace App\Services\Images;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

/**
 * Brings an image under a byte limit WITHOUT cropping: the picture keeps its whole frame and aspect ratio.
 * Only the encoding is tightened — quality steps down gently (JPEG / WebP; a PNG becomes a WebP, which keeps transparency),
 * and the pixels are reduced ONLY when even the lowest allowed quality is still too big. A picture already under the limit,
 * an animated GIF, or a format the server cannot decode is returned untouched (never fails an upload by itself).
 *
 * Before that, {@see normalize()} makes ANY image the browser can show: a web type with an odd name (photo.jfif, .jpe, .pjpeg…)
 * only gets the right extension, and a type browsers cannot draw (HEIC/HEIF, AVIF, TIFF, BMP, ICO…) is converted
 * (photos from a phone → JPEG, the rest → WebP, which keeps transparency).
 */
class ImageShrinker
{
    /** About 100 KB (a little under, so it holds however KB is counted). */
    public const LIMIT_BYTES = 100_000;

    /** Quality steps tried at full size, best first. */
    private const QUALITIES = [90, 82, 74, 66, 58, 50, 42];

    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'webp', 'image/webp' => 'webp'];

    /** Types every browser draws, with the extensions they may be stored under (first = the one we rename to). */
    private const WEB_TYPES = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/gif' => ['gif'],
        'image/svg+xml' => ['svg'],
    ];

    /** Non-web types that are photos (no transparency): converted to JPEG. Every other image type becomes WebP. */
    private const PHOTO_TYPES = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];

    public static function shrink(UploadedFile $file, int $limit = self::LIMIT_BYTES): UploadedFile
    {
        $file = self::normalize($file);

        try {
            $mime = (string) $file->getMimeType();

            if (! isset(self::MIME[$mime]) || ! $file->isValid() || $file->getSize() <= $limit || self::isAnimated($file->getRealPath(), $mime)) {
                return $file;
            }

            if (function_exists('ini_set')) {
                @ini_set('memory_limit', '1024M');
            }

            $extension = self::MIME[$mime];
            $outMime = $extension === 'jpg' ? 'image/jpeg' : 'image/webp';
            $source = Image::decodePath($file->getRealPath());
            $bytes = null;

            foreach (self::QUALITIES as $quality) {
                $bytes = self::encode($source, $extension, $quality);

                if (strlen($bytes) <= $limit) {
                    return self::wrap($file, $bytes, $extension, $outMime);
                }
            }

            // Last resort: the same picture with fewer pixels (never enlarged, never cropped).
            for ($step = 0; $step < 20; $step++) {
                $source = $source->scale(width: max(64, (int) round($source->width() * 0.9)));
                $bytes = self::encode($source, $extension, 60);

                if (strlen($bytes) <= $limit || $source->width() <= 64) {
                    break;
                }
            }

            return self::wrap($file, (string) $bytes, $extension, $outMime);
        } catch (Throwable $e) {
            report($e);

            return $file;
        }
    }

    /**
     * Gives an uploaded image a type and extension browsers can show. The type is read from the file's CONTENT, never trusted
     * from its name. Not an image, or a file the server cannot read, is returned untouched.
     */
    public static function normalize(UploadedFile $file): UploadedFile
    {
        try {
            if (! $file->isValid()) {
                return $file;
            }

            $mime = strtolower((string) $file->getMimeType());

            if (! str_starts_with($mime, 'image/')) {
                return $file;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());

            if (isset(self::WEB_TYPES[$mime])) {
                return in_array($extension, self::WEB_TYPES[$mime], true)
                    ? $file
                    : self::wrap($file, (string) file_get_contents($file->getRealPath()), self::WEB_TYPES[$mime][0], $mime);
            }

            return self::convert($file, $mime) ?? $file;
        } catch (Throwable $e) {
            report($e);

            return $file;
        }
    }

    /** HEIC/AVIF/TIFF/BMP/ICO… → JPEG or WebP (first frame, turned upright). Null when the server cannot read it. */
    private static function convert(UploadedFile $file, string $mime): ?UploadedFile
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        $toJpeg = in_array($mime, self::PHOTO_TYPES, true);

        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '1024M');
        }

        $image = new \Imagick;
        $image->readImage($file->getRealPath().'[0]');
        $image->autoOrient();
        $image->stripImage();

        if ($toJpeg) {
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        }

        $image->setImageFormat($toJpeg ? 'jpeg' : 'webp');
        $image->setImageCompressionQuality(90);
        $bytes = $image->getImagesBlob();
        $image->clear();

        if ($bytes === '') {
            return null;
        }

        return $toJpeg
            ? self::wrap($file, $bytes, 'jpg', 'image/jpeg')
            : self::wrap($file, $bytes, 'webp', 'image/webp');
    }

    private static function encode($image, string $extension, int $quality): string
    {
        return (string) $image->encodeUsingFormat($extension === 'jpg' ? Format::JPEG : Format::WEBP, quality: $quality);
    }

    private static function wrap(UploadedFile $original, string $bytes, string $extension, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'shrunk_');
        file_put_contents($path, $bytes);
        register_shutdown_function(static fn () => @unlink($path));

        $name = pathinfo($original->getClientOriginalName(), PATHINFO_FILENAME) ?: 'image';

        return new UploadedFile($path, $name.'.'.$extension, $mime, null, true);
    }

    /** Only a WebP can be animated among what we re-encode; a re-encode would flatten it to one frame. */
    private static function isAnimated(string $path, string $mime): bool
    {
        if ($mime !== 'image/webp') {
            return false;
        }

        return str_contains((string) file_get_contents($path, false, null, 0, 65536), 'ANIM');
    }
}
