<?php

namespace Modules\SystemBase\app\Services;

use Modules\SystemBase\app\Services\Base\BaseService;

class LivewireService extends BaseService
{
    public static function getKey(string $key, bool $addUnique = true): string
    {
        if ($addUnique) {
            $key = uniqid($key.'-');
        }
        return $key;
    }
}