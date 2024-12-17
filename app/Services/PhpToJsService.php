<?php

namespace Modules\SystemBase\app\Services;


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
        data_set($this->data, $key, $data);
    }

    /**
     * @param  string  $key
     * @return array|mixed
     */
    public function get(string $key): mixed
    {
        return data_get($this->data, $key);
    }

    /**
     * @return false|string
     */
    public function toJson(): false|string
    {
        return json_encode($this->data);
    }

}