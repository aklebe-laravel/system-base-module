<?php

namespace Modules\SystemBase\app\Services\Base;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Base Class for (nearly) every Service.
 */
class BaseService
{
    /**
     * Debug mode is set by default from config/env
     *
     * @var bool
     */
    public bool $debugMode = false;

    /**
     * @var array
     */
    protected array $errorBag = [];

    /**
     * @var string
     */
    protected string $indentKey = 'global_base_service_indent';

    /**
     *
     */
    public function __construct()
    {
        $this->debugMode = config('app.debug', false);
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errorBag;
    }

    /**
     * @return int
     */
    public function getIndentValue(): int
    {
        return Cache::store('array')->get($this->indentKey, 0);
    }

    /**
     * @return void
     */
    public function incrementIndent(): void
    {
        $v = $this->getIndentValue() + 1;
        Cache::store('array')->set($this->indentKey, $v);
    }

    /**
     * @param  int  $count
     * @return void
     */
    public function decrementIndent(int $count = 1): void
    {
        $v = $this->getIndentValue() - $count;
        if ($v < 0) {
            $v = 0;
        }
        Cache::store('array')->set($this->indentKey, $v);
    }

    /**
     * @return string
     */
    public function getIndentString(): string
    {
        return str_pad('', $this->getIndentValue() * 2);
    }

    /**
     * @param $message
     * @param  array  $context
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        if ($this->debugMode) {
            if (!is_scalar($message)) {
                $message = print_r($message, true);
            }
            Log::debug($this->getIndentString().$message, $context);
        }
    }

    /**
     * @param $message
     * @param  array  $context
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->errorBag[] = $message;
        if (!is_scalar($message)) {
            $message = print_r($message, true);
        }
        Log::warning($this->getIndentString().$message, $context);
    }

    /**
     * @param $message
     * @param  array  $context
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->errorBag[] = $message;
        if (!is_scalar($message)) {
            $message = print_r($message, true);
        }
        Log::error($this->getIndentString().$message, $context);
    }

    /**
     * @param $message
     * @param  array  $context
     * @return void
     */
    public function info($message, array $context = []): void
    {
        if (!is_scalar($message)) {
            $message = print_r($message, true);
        }
        Log::info($this->getIndentString().$message, $context);
    }


}