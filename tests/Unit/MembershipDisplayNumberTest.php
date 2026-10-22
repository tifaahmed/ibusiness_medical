<?php

namespace Tests\Unit;

use App\Models\Membership;
use PHPUnit\Framework\TestCase;

class MembershipDisplayNumberTest extends TestCase
{
    /** @test */
    public function six_digits_are_printed_with_a_dash_after_the_first_three(): void
    {
        $this->assertSame('123-456', Membership::displayNumber('123456'));
    }

    /** @test */
    public function other_numbers_are_printed_as_they_are(): void
    {
        $this->assertSame('1234', Membership::displayNumber('1234'));
        $this->assertSame('REG-ab12', Membership::displayNumber('REG-ab12'));
        $this->assertSame('1234567', Membership::displayNumber('1234567'));
    }
}
