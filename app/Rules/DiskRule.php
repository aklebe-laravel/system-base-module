<?php

namespace Modules\SystemBase\app\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value has to be a disk configured in 'filesystems.disks'.
 */
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