<?php

namespace Tests\Unit;

use App\Http\Middleware\ShrinkUploadedImages;
use App\Services\Images\ImageShrinker;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImageShrinkerTest extends TestCase
{
    private function noisy(string $type, int $w, int $h): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        for ($i = 0; $i < $w * $h / 40; $i++) {
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
        }
        for ($y = 0; $y < $h; $y += 8) {
            imageline($im, 0, $y, $w, $h - $y, random_int(0, 0xFFFFFF));
        }
        $path = tempnam(sys_get_temp_dir(), 'img_');
        $type === 'png' ? imagepng($im, $path, 0) : imagejpeg($im, $path, 100);

        return new UploadedFile($path, 'photo.'.$type, $type === 'png' ? 'image/png' : 'image/jpeg', null, true);
    }

    public function test_big_jpeg_goes_under_the_limit_with_the_same_frame(): void
    {
        $file = $this->noisy('jpg', 2600, 1800);
        $this->assertGreaterThan(ImageShrinker::LIMIT_BYTES, $file->getSize());

        $out = ImageShrinker::shrink($file);

        $this->assertLessThanOrEqual(ImageShrinker::LIMIT_BYTES, $out->getSize());
        [$w, $h] = getimagesize($out->getRealPath());
        $this->assertEqualsWithDelta(2600 / 1800, $w / $h, 0.02);
    }

    public function test_big_png_becomes_a_small_webp(): void
    {
        $out = ImageShrinker::shrink($this->noisy('png', 2000, 2000));

        $this->assertLessThanOrEqual(ImageShrinker::LIMIT_BYTES, $out->getSize());
        $this->assertSame('image/webp', $out->getMimeType());
    }

    public function test_a_small_image_is_returned_untouched(): void
    {
        $file = $this->noisy('jpg', 40, 40);

        $this->assertSame($file, ImageShrinker::shrink($file));
    }

    public function test_the_middleware_shrinks_nested_files_and_leaves_other_files(): void
    {
        $pdf = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($pdf, '%PDF-1.4 '.str_repeat('x', 1_500_000));
        $request = Request::create('/x', 'POST', [], [], [
            'gallery' => [$this->noisy('jpg', 2600, 1800)],
            'contract' => new UploadedFile($pdf, 'c.pdf', 'application/pdf', null, true),
        ]);

        (new ShrinkUploadedImages)->handle($request, fn ($r) => response('ok'));

        $this->assertLessThanOrEqual(ImageShrinker::LIMIT_BYTES, $request->file('gallery')[0]->getSize());
        $this->assertGreaterThan(ImageShrinker::LIMIT_BYTES, $request->file('contract')->getSize());
    }
}
