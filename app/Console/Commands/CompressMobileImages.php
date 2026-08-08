<?php

namespace App\Console\Commands;

use App\Models\Facility;
use Illuminate\Console\Command;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CompressMobileImages extends Command
{
    protected $signature = 'facility:compress-mobile-images
                           {--image-width=800 : Max width in pixels for mobile_image}
                           {--logo-width=300 : Max width in pixels for mobile_logo}
                           {--jpeg-quality=70 : JPEG quality (1-100)}
                           {--png-quality=60-80 : PNG quant quality range}';

    protected $description = 'Re-copy facility images and logos to mobile, then compress/resize them';

    public function handle(): void
    {
        $facilities = Facility::all();
        $bar = $this->output->createProgressBar($facilities->count());
        $bar->start();

        $manager = new ImageManager(new Driver());
        $jpegQuality = (int) $this->option('jpeg-quality');
        $pngQuality = $this->option('png-quality');

        $pairs = [
            'image' => ['mobile_image', (int) $this->option('image-width')],
            'logo' => ['mobile_logo', (int) $this->option('logo-width')],
        ];

        $copied = 0;
        $compressed = 0;
        $errors = 0;

        foreach ($facilities as $facility) {
            foreach ($pairs as $source => [$target, $maxWidth]) {
                $normal = $facility->getFirstMedia($source);

                if (!$normal) {
                    continue;
                }

                try {
                    $existing = $facility->getFirstMedia($target);
                    if ($existing) {
                        $existing->delete();
                    }

                    $mobile = $normal->copy($facility, $target);
                    $copied++;

                    $path = $mobile->getPath();

                    if (!file_exists($path)) {
                        continue;
                    }

                    $ext = strtolower(pathinfo($mobile->file_name, PATHINFO_EXTENSION));

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
