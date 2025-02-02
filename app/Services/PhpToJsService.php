<?php

namespace Modules\SystemBase\app\Services;


use Illuminate\Support\Arr;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * As singleton.
 * Use: app('php_to_js')->...
 */
class PhpToJsService extends BaseService
{
    protected array $data = [];

    /**
     * Add data for frontend javascript.
     *
     * @param  string  $key
     * @param  mixed  $data
     * @return void
     */
    public function addData(string $key, mixed $data): void
    {
        Arr::set($this->data, $key, $data);
    }

    /**
     * @param  string  $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * @param  string  $key
     * @return array|mixed
     */
    public function get(string $key): mixed
    {
        return Arr::get($this->data, $key);
    }

    /**
     * @return false|string
     */
    public function toJson(): false|string
    {
        return json_encode($this->data);
    }

}