<?php

namespace Modules\SystemBase\app\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DirectoryRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
//        if (strtoupper($value) !== $value) {
//            $fail('The :attribute must be uppercase.');
//        }

        if (!is_dir($value)) {
            $fail(sprintf("Parameter :attribute is not a directory: %s", $value));
        }

        $value = realpath($value);
        if (!is_dir($value)) {
            $fail(sprintf("Error in realpath() for parameter :attribute: %s", $value));
        }

    }
}