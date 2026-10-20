<?php

namespace Tests\Unit;

use App\Support\StringFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StringFormatterTest extends TestCase
{
    #[Test]
    public function contains_ignore_case_matches_needles_regardless_of_case(): void
    {
        $this->assertTrue(StringFormatter::containsIgnoreCase('The request timed out.', 'timed out'));
        $this->assertTrue(StringFormatter::containsIgnoreCase('The request TIMED OUT.', 'Timed Out'));
        $this->assertFalse(StringFormatter::containsIgnoreCase('connection refused', 'timed out'));
        $this->assertFalse(StringFormatter::containsIgnoreCase(null, 'timed out'));
        $this->assertFalse(StringFormatter::containsIgnoreCase(['timed out'], 'timed out'));
    }

    #[Test]
    public function lower_lowercases_strings_and_yields_empty_for_non_strings(): void
    {
        $this->assertSame('mixed case', StringFormatter::lower('MiXeD Case'));
        $this->assertSame('0', StringFormatter::lower('0'));
        $this->assertSame("a\xFF", StringFormatter::lower("A\xFF"));
        $this->assertSame('', StringFormatter::lower(null));
        $this->assertSame('', StringFormatter::lower(['A']));
    }

    #[Test]
    public function null_if_blank_returns_trimmed_value_or_null(): void
    {
        $this->assertSame('x', StringFormatter::nullIfBlank('  x  '));
        $this->assertSame('0', StringFormatter::nullIfBlank('0'));
        $this->assertNull(StringFormatter::nullIfBlank(''));
        $this->assertNull(StringFormatter::nullIfBlank('   '));
        $this->assertNull(StringFormatter::nullIfBlank(null));
        $this->assertNull(StringFormatter::nullIfBlank(['x']));
    }

    #[Test]
    public function trim_strips_characters_from_strings_and_yields_empty_for_non_strings(): void
    {
        $this->assertSame('hi', StringFormatter::trim('  hi  '));
        $this->assertSame('x', StringFormatter::trim('--x--', '-'));
        $this->assertSame('0', StringFormatter::trim('0'));
        $this->assertSame('', StringFormatter::trim(null));
        $this->assertSame('', StringFormatter::trim(['hi']));
        $this->assertSame('', StringFormatter::trim('   '));
    }
}
