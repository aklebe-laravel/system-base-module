<?php

namespace Modules\SystemBase\app\Services;

use Modules\SystemBase\app\Services\Base\BaseService;
use function Illuminate\Filesystem\join_paths;

class PathService extends BaseService
{
    /**
     * @param  string  $basePath
     * @param          ...$paths
     *
     * @return string
     */
    public function combine(string $basePath, ...$paths): string
    {
        return join_paths($basePath, ...$paths);
    }

}