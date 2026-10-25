<?php

namespace Modules\SystemBase\app\Services;

use Modules\SystemBase\app\Services\Base\BaseService;
use function Illuminate\Filesystem\join_paths;

/**
 * Path helpers. As singleton: app('system_base_path').
 */
class PathService extends BaseService
{
    /**
     * Joins path parts with the directory separator, empty parts are skipped.
     *
     * @param  string  $basePath
     * @param  string  ...$paths
     *
     * @return string
     */
    public function combine(string $basePath, ...$paths): string
    {
        return join_paths($basePath, ...$paths);
    }

}