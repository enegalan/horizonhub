<?php

namespace Tests\Unit;

use App\Rules\CommaSeparatedEmailList;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommaSeparatedEmailListRuleTest extends TestCase
{
    #[Test]
    public function allows_empty_and_valid_email_lists(): void
    {
        $rule = new CommaSeparatedEmailList;

        foreach (['', '  ', 'a@example.com', ' a@example.com , b@example.com '] as $value) {
            $failed = false;
            $rule->validate('email_to', $value, function () use (&$failed): void {
                $failed = true;
            });
            $this->assertFalse($failed, 'Expected pass for value: ' . \var_export($value, true));
        }
    }

    #[Test]
    public function fails_for_invalid_emails_and_non_strings(): void
    {
        $rule = new CommaSeparatedEmailList;

        foreach (['not-an-email', 'a@example.com, missing-at.example.com', ['a@example.com']] as $value) {
            $failed = false;
            $rule->validate('email_to', $value, function () use (&$failed): void {
                $failed = true;
            });
            $this->assertTrue($failed, 'Expected fail for value: ' . \var_export($value, true));
        }
    }
}
