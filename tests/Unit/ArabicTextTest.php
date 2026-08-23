<?php

namespace Tests\Unit;

use App\Support\ArabicText;
use Tests\TestCase;

/**
 * The card PNG is drawn with GD, which neither joins Arabic letters nor
 * reverses right-to-left runs. These are the shapes that get printed, so the
 * expectations here are written as explicit presentation-form code points
 * rather than as Arabic literals a text editor might silently re-order.
 */
class ArabicTextTest extends TestCase
{
    /** @test */
    public function the_card_slogan_comes_out_joined_and_right_to_left(): void
    {
        // ص ح ت ك · و ا ك ت ر, read back to front and in joined shapes.
        $this->assertSame(
            self::glyphs([0xFEAE, 0xFE98, 0xFEDB, 0x0627, 0x0648, 0x0020, 0xFEDA, 0xFE98, 0xFEA4, 0xFEBB]),
            ArabicText::forRendering('صحتك واكتر'),
        );
    }

    /** @test */
    public function a_lam_followed_by_an_alef_becomes_one_ligature(): void
    {
        $this->assertSame(self::glyphs([0xFEFB]), ArabicText::forRendering('لا'));

        // الله: alef, lam initial, lam medial, ha final — reversed for drawing.
        $this->assertSame(
            self::glyphs([0xFEEA, 0xFEE0, 0xFEDF, 0x0627]),
            ArabicText::forRendering('الله'),
        );
    }

    /** @test */
    public function a_letter_standing_alone_keeps_its_plain_code_point(): void
    {
        // Tajawal ships no U+FExx isolated glyphs, so an unjoined alef or waw
        // has to go to the font as U+0627 / U+0648 or it draws as a box.
        $this->assertSame(self::glyphs([0x0648]), ArabicText::forRendering('و'));
        $this->assertSame(self::glyphs([0x0627, 0x0648]), ArabicText::forRendering('وا'));
    }

    /** @test */
    public function numbers_inside_an_arabic_line_still_read_left_to_right(): void
    {
        $rendered = ArabicText::forRendering('رقم 6593');

        $this->assertStringEndsWith(self::glyphs([0xFEE2, 0xFED7, 0x0631]), $rendered);
        $this->assertStringStartsWith('6593', $rendered);
    }

    /** @test */
    public function latin_only_fields_are_left_exactly_as_they_were(): void
    {
        $this->assertSame('www.deilar.com', ArabicText::forRendering('www.deilar.com'));
        $this->assertSame('8 / 2027', ArabicText::forRendering('8 / 2027'));
        $this->assertSame('', ArabicText::forRendering(''));
    }

    /** @test */
    public function a_harakah_stays_attached_to_the_letter_it_sits_on(): void
    {
        // بَ — the fathah must still follow its base after the run is reversed.
        $this->assertSame(
            self::glyphs([0x0628, 0x064E]),
            ArabicText::forRendering("\u{0628}\u{064E}"),
        );
    }

    /**
     * @param  array<int, int>  $codepoints
     */
    private static function glyphs(array $codepoints): string
    {
        return implode('', array_map(fn (int $cp): string => mb_chr($cp, 'UTF-8'), $codepoints));
    }
}
