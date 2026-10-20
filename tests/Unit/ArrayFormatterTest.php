<?php

namespace Tests\Unit;

use App\Support\ArrayFormatter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArrayFormatterTest extends TestCase
{
    #[Test]
    public function it_returns_arrays_unchanged_and_empty_for_non_arrays(): void
    {
        $this->assertSame(['a', 'b'], ArrayFormatter::orEmpty(['a', 'b']));
        $this->assertSame(['x' => 1], ArrayFormatter::orEmpty(['x' => 1]));
        $this->assertSame([], ArrayFormatter::orEmpty([]));
        $this->assertSame([], ArrayFormatter::orEmpty(null));
        $this->assertSame([], ArrayFormatter::orEmpty('x'));
        $this->assertSame([], ArrayFormatter::orEmpty(123));
        $this->assertSame([], ArrayFormatter::orEmpty((object) ['a' => 1]));
    }
}
