<?php

namespace Tests\Unit;

use App\Support\PhoneNumbers;
use PHPUnit\Framework\TestCase;

/**
 * Imported branch cells packed several numbers into one string with whatever
 * separator the branch used. These are the real shapes we found in the data;
 * every one has to come back as a list of numbers that each fit the 20-char
 * branch-phone limit.
 */
class PhoneNumbersTest extends TestCase
{
    /** @test */
    public function it_splits_on_a_slash(): void
    {
        $this->assertSame(
            ['01277555513', '01200003050', '01210561111', '0226124789'],
            PhoneNumbers::split('01277555513/01200003050/01210561111/0226124789'),
        );
    }

    /** @test */
    public function it_splits_on_a_spaced_hyphen_but_keeps_a_hyphen_inside_a_number(): void
    {
        $this->assertSame(
            ['0225288852', '01066470013'],
            PhoneNumbers::split('0225288852 - 01066470013'),
        );

        $this->assertSame(['02-33046378'], PhoneNumbers::split('02-33046378'));
    }

    /** @test */
    public function it_folds_arabic_indic_digits_to_ascii(): void
    {
        $this->assertSame(['01119684639'], PhoneNumbers::split('٠١١١٩٦٨٤٦٣٩'));
    }

    /** @test */
    public function it_strips_spaces_from_inside_a_number(): void
    {
        $this->assertSame(['0663400006'], PhoneNumbers::split('066 3400006'));
        $this->assertSame(
            ['0663400006', '01023307060'],
            PhoneNumbers::split('066 3400006 / 01023307060'),
        );
    }

    /** @test */
    public function it_accepts_an_array_and_drops_exact_duplicates(): void
    {
        $this->assertSame(
            ['+20223314746', '01066310003'],
            PhoneNumbers::split(['+20223314746', '01066310003', '01066310003']),
        );
    }

    /** @test */
    public function it_breaks_two_numbers_glued_with_a_bare_hyphen(): void
    {
        $this->assertSame(
            ['0233046378', '01210541111', '01013030084'],
            PhoneNumbers::split('02-33046378-01210541111 / 01013030084'),
        );
    }

    /** @test */
    public function every_number_it_returns_fits_the_branch_phone_limit(): void
    {
        $raw = [
            '01113279759 - 01289104343 - 01025322296',
            '066 3400006 / 01023307060 / 01208999584',
            '(202)25364042/1-01221504405/01028000095',
        ];

        foreach ($raw as $value) {
            foreach (PhoneNumbers::split($value) as $number) {
                $this->assertLessThanOrEqual(PhoneNumbers::MAX_LENGTH, mb_strlen($number), "too long: {$number}");
            }
        }
    }

    /** @test */
    public function it_returns_an_empty_list_for_blank_input(): void
    {
        $this->assertSame([], PhoneNumbers::split(null));
        $this->assertSame([], PhoneNumbers::split('   '));
        $this->assertSame([], PhoneNumbers::split([]));
    }
}
