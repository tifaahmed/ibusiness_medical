<?php

namespace App\Console\Commands;

use App\Models\Offer;
use Illuminate\Console\Command;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CompressOfferMobileImages extends Command
{
    protected $signature = 'offer:compress-mobile-images
                           {--image-width=800 : Max width in pixels for mobile_image}
                           {--thumbnail-width=400 : Max width in pixels for mobile_thumbnail}
                           {--jpeg-quality=70 : JPEG quality (1-100)}
                           {--png-quality=60-80 : PNG quant quality range}';

    protected $description = 'Re-copy offer images to mobile, then compress/resize them';

    public function handle(): void
    {
        $offers = Offer::all();
        $bar = $this->output->createProgressBar($offers->count());
        $bar->start();

        $manager = new ImageManager(new Driver());
        $jpegQuality = (int) $this->option('jpeg-quality');
        $pngQuality = $this->option('png-quality');

        $pairs = [
            'image' => 'mobile_image',
            'thumbnail' => 'mobile_thumbnail',
        ];

        $widths = [
            'mobile_image' => (int) $this->option('image-width'),
            'mobile_thumbnail' => (int) $this->option('thumbnail-width'),
        ];

        $copied = 0;
        $compressed = 0;
        $errors = 0;

        foreach ($offers as $offer) {
            foreach ($pairs as $source => $target) {
                $normal = $offer->getFirstMedia($source);

                if (!$normal) {
                    continue;
                }

                try {
                    $existing = $offer->getFirstMedia($target);
                    if ($existing) {
                        $existing->delete();
                    }

                    $mobile = $normal->copy($offer, $target);
                    $copied++;

                    $path = $mobile->getPath();

                    if (!file_exists($path)) {
                        continue;
                    }

                    $ext = strtolower(pathinfo($mobile->file_name, PATHINFO_EXTENSION));
                    $maxWidth = $widths[$target];

                    if (in_array($ext, ['jpg', 'jpeg', 'jpe'])) {
                        $image = $manager->decode($path);
                        $image->scaleDown(width: $maxWidth);
                        $image->save($path, quality: $jpegQuality);
                        $compressed++;
                    } elseif ($ext === 'png') {
                        try {
                            $image = $manager->decode($path);
                            $image->scaleDown(width: $maxWidth);
                            $image->save($path);
                        } catch (\Exception $e) {
                        }

                        $this->compressPng($path, $pngQuality) && $compressed++;
                    } elseif ($ext === 'webp') {
                        $image = $manager->decode($path);
                        $image->scaleDown(width: $maxWidth);
                        $image->save($path, quality: $jpegQuality);
                        $compressed++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! {$copied} re-copied, {$compressed} compressed, {$errors} errors.");
    }

    private function compressPng(string $path, string $quality): bool
    {
        $quantPath = tempnam(sys_get_temp_dir(), 'quant_') . '.png';
        $cmd = sprintf(
            'pngquant --quality=%s --force --output=%s %s 2>/dev/null',
            escapeshellarg($quality),
            escapeshellarg($quantPath),
            escapeshellarg($path)
        );
        exec($cmd, $output, $exitCode);

        $success = false;

        if ($exitCode === 0 && file_exists($quantPath)) {
            $quantSize = filesize($quantPath);
            $origSize = filesize($path);
            if ($quantSize > 0 && $quantSize < $origSize) {
                copy($quantPath, $path);
                $success = true;
            }
        }

        if (file_exists($quantPath)) {
            unlink($quantPath);
        }

        return $success;
    }
}
