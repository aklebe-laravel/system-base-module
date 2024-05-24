<?php

namespace Modules\SystemBase\app\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DiskRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!config()->has('filesystems.disks.'.$value)) {
            $fail(sprintf("Missing configured disk '%s' for parameter :attribute.", $value));
        }
    }
}