<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts YYYY-MM-DD or YYYY-MM-DDTHH:MM (no timezone).
 */
final class RetryModalDateFilter implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if (! \is_string($value)) {
            $fail(__('validation.date', ['attribute' => $attribute]));

            return;
        }
        $trimmed = \trim($value);
        if ($trimmed === '') {
            return;
        }
        if (\preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $trimmed) !== 1) {
            $fail(__('validation.date', ['attribute' => $attribute]));

            return;
        }
        try {
            Carbon::parse($trimmed);
        } catch (\Throwable) {
            $fail(__('validation.date', ['attribute' => $attribute]));
        }
    }
}
