<?php

namespace Tests\Unit;

use App\Models\PushNotification;
use PHPUnit\Framework\TestCase;

class PushAudiencePhonesTest extends TestCase
{
    /** @test */
    public function it_splits_lines_commas_and_spaces_keeps_digits_and_drops_repeats(): void
    {
        $this->assertSame(
            ['01011111111', '01022222222', '01033333333'],
            PushNotification::parsePhones("01011111111,\n01022222222 ; 01011111111\r\n+01033333333،"),
        );
        $this->assertSame([], PushNotification::parsePhones("  \n , "));
    }
}
