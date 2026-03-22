<?php

namespace Tests\Unit;

use App\Rules\RetryModalDateFilter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RetryModalDateFilterRuleTest extends TestCase
{
    #[Test]
    public function passes_for_date_only_and_datetime_strings(): void
    {
        $rule = new RetryModalDateFilter;
        $failed = false;
        $rule->validate('date_from', '2026-03-21', function () use (&$failed): void {
            $failed = true;
        });
        $this->assertFalse($failed);

        $rule->validate('date_from', '2026-03-21T15:03', function () use (&$failed): void {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    #[Test]
    public function fails_for_placeholder_like_strings(): void
    {
        $rule = new RetryModalDateFilter;
        $failed = false;
        $rule->validate('date_from', '2026-03-ddTHH:03', function () use (&$failed): void {
            $failed = true;
        });
        $this->assertTrue($failed);
    }

    #[Test]
    public function allows_null_and_empty(): void
    {
        $rule = new RetryModalDateFilter;
        foreach ([null, '', '   '] as $value) {
            $failed = false;
            $rule->validate('date_from', $value, function () use (&$failed): void {
                $failed = true;
            });
            $this->assertFalse($failed, 'Expected pass for value: '.\var_export($value, true));
        }
    }
}
