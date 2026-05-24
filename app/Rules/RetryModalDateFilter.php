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
        if (blank($value)) {
            return;
        }

        if (! \is_string($value)) {
            $fail(__('validation.date', ['attribute' => $attribute]));

            return;
        }

        $value = \trim($value);

        if ($value === '') {
            return;
        }

        if (\preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $value) !== 1) {
            $fail(__('validation.date', ['attribute' => $attribute]));

            return;
        }

        try {
            Carbon::parse($value);
        } catch (\Throwable) {
            $fail(__('validation.date', ['attribute' => $attribute]));
        }
    }
}
