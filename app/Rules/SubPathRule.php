<?php

namespace Modules\SystemBase\app\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SubPathRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = app('system_base_file')->getValidPath($value);
        if ($path != $value) {
            $fail(sprintf("Parameter :attribute has an invalid path: '%s'. Should be '%s'.", $value, $path));
        }
    }
}