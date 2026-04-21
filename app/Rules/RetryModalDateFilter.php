<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class RetryModalDateFilter implements ValidationRule
{
    /**
     * Validate the date filter.
     * Accepts YYYY-MM-DD or YYYY-MM-DDTHH:MM (no timezone).
     *
     * @param string $attribute The attribute name.
     * @param mixed $value The value to validate.
     * @param Closure $fail The closure to call if the validation fails.
     */
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
