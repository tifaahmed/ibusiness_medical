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
 */
class ImageShrinker
{
    /** A little under 1 MiB, so "1 MB" holds whichever way it is counted. */
    public const LIMIT_BYTES = 1_000_000;

    /** Quality steps tried at full size, best first. */
    private const QUALITIES = [92, 88, 84, 80, 76, 72, 68];

    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'webp', 'image/webp' => 'webp'];

    public static function shrink(UploadedFile $file, int $limit = self::LIMIT_BYTES): UploadedFile
    {
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
                $bytes = self::encode($source, $extension, 72);

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
