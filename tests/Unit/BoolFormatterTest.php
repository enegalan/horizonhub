<?php

namespace Tests\Unit;

use App\Support\BoolFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BoolFormatterTest extends TestCase
{
    #[Test]
    public function or_null_coerces_configured_values_and_keeps_null(): void
    {
        $this->assertTrue(BoolFormatter::orNull(true));
        $this->assertTrue(BoolFormatter::orNull('true'));
        $this->assertTrue(BoolFormatter::orNull('1'));
        $this->assertTrue(BoolFormatter::orNull(1));
        $this->assertFalse(BoolFormatter::orNull(false));
        $this->assertFalse(BoolFormatter::orNull('false'));
        $this->assertFalse(BoolFormatter::orNull('0'));
        $this->assertFalse(BoolFormatter::orNull(0));
        $this->assertNull(BoolFormatter::orNull(null));
    }
}
