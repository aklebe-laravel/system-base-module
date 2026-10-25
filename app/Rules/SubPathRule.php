<?php

namespace Modules\SystemBase\app\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value has to be a valid path (unchanged by FileService::getValidPath()).
 */
class SubPathRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = app('system_base_file')->getValidPath((string) $value);
        if ($path !== (string) $value) {
            $fail(sprintf("Parameter :attribute has an invalid path: '%s'. Should be '%s'.", $value, $path));
        }
    }
}