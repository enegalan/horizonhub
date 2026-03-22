<?php

namespace Tests\Unit;

use App\Support\Horizon\RetryModalDatetimeBoundaries;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetryModalDatetimeBoundariesTest extends TestCase
{
    #[Test]
    public function parse_lower_date_only_is_start_of_day(): void
    {
        $c = RetryModalDatetimeBoundaries::parseLower('2026-01-10');
        $this->assertNotNull($c);
        $this->assertSame('2026-01-10 00:00:00', $c->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function parse_upper_date_only_is_end_of_day(): void
    {
        $c = RetryModalDatetimeBoundaries::parseUpper('2026-01-10');
        $this->assertNotNull($c);
        $this->assertSame('2026-01-10 23:59:59', $c->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function parse_lower_datetime_preserves_instant(): void
    {
        $c = RetryModalDatetimeBoundaries::parseLower('2026-01-10T14:30');
        $this->assertNotNull($c);
        $this->assertSame('2026-01-10 14:30:00', $c->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function parse_upper_datetime_preserves_instant(): void
    {
        $c = RetryModalDatetimeBoundaries::parseUpper('2026-01-10T14:30');
        $this->assertNotNull($c);
        $this->assertSame('2026-01-10 14:30:00', $c->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function empty_strings_return_null(): void
    {
        $this->assertNull(RetryModalDatetimeBoundaries::parseLower(''));
        $this->assertNull(RetryModalDatetimeBoundaries::parseLower(null));
        $this->assertNull(RetryModalDatetimeBoundaries::parseUpper('   '));
    }
}
