<?php

namespace App\Rules;

use App\Support\StringFormatter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class CommaSeparatedEmailList implements ValidationRule
{
    /**
     * Validate a comma-separated list of email addresses.
     * An empty value is allowed so conditional required rules can gate it,
     * but nonblank input must yield at least one email address.
     *
     * @param string $attribute The attribute name.
     * @param mixed $value The value to validate.
     * @param Closure $fail The closure to call if the validation fails.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! \is_string($value)) {
            $fail(__('validation.string', ['attribute' => $attribute]));

            return;
        }

        $emails = \array_filter(\array_map('trim', \explode(',', $value)));

        if (StringFormatter::trim($value) !== '' && empty($emails)) {
            $fail(__('validation.email', ['attribute' => $attribute]));

            return;
        }

        foreach ($emails as $email) {
            if (! \filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fail(__('validation.email', ['attribute' => $attribute]));

                return;
            }
        }
    }
}
