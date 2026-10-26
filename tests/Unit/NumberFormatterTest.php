<?php

namespace Tests\Unit;

use App\Support\NumberFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NumberFormatterTest extends TestCase
{
    #[Test]
    public function count_accepts_arrays_and_countables(): void
    {
        $this->assertSame(0, NumberFormatter::count(null));
        $this->assertSame(0, NumberFormatter::count('x'));
        $this->assertSame(0, NumberFormatter::count(12));
        $this->assertSame(0, NumberFormatter::count([]));
        $this->assertSame(2, NumberFormatter::count(['a', 'b']));
        $this->assertSame(3, NumberFormatter::count(new \ArrayObject([1, 2, 3])));
    }

    #[Test]
    public function float_or_null_coerces_numeric_values(): void
    {
        $this->assertSame(1.5, NumberFormatter::floatOrNull(1.5));
        $this->assertSame(2.0, NumberFormatter::floatOrNull('2'));
        $this->assertSame(0.0, NumberFormatter::floatOrNull(0));
        $this->assertNull(NumberFormatter::floatOrNull(null));
        $this->assertNull(NumberFormatter::floatOrNull('x'));
        $this->assertNull(NumberFormatter::floatOrNull([]));
    }

    #[Test]
    public function int_or_null_coerces_numeric_values(): void
    {
        $this->assertSame(3, NumberFormatter::intOrNull(3));
        $this->assertSame(4, NumberFormatter::intOrNull('4'));
        $this->assertSame(0, NumberFormatter::intOrNull(0));
        $this->assertSame(-2, NumberFormatter::intOrNull('-2'));
        $this->assertNull(NumberFormatter::intOrNull(null));
        $this->assertNull(NumberFormatter::intOrNull('x'));
        $this->assertNull(NumberFormatter::intOrNull([]));
    }

    #[Test]
    public function non_negative_int_clamps_and_defaults_to_zero(): void
    {
        $this->assertSame(5, NumberFormatter::nonNegativeInt(5));
        $this->assertSame(5, NumberFormatter::nonNegativeInt('5'));
        $this->assertSame(0, NumberFormatter::nonNegativeInt(-3));
        $this->assertSame(0, NumberFormatter::nonNegativeInt(null));
        $this->assertSame(0, NumberFormatter::nonNegativeInt('x'));
        $this->assertSame(0, NumberFormatter::nonNegativeInt([]));
    }
}
