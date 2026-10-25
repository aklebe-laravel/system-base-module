<?php

namespace Modules\SystemBase\app\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value has to be an existing directory.
 */
class DirectoryRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !is_dir($value)) {
            $fail(sprintf("Parameter :attribute is not a directory: %s", is_scalar($value) ? $value : gettype($value)));

            return;
        }

        if (($realPath = realpath($value)) === false || !is_dir($realPath)) {
            $fail(sprintf("Error in realpath() for parameter :attribute: %s", $value));
        }
    }
}