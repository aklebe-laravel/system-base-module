<?php

namespace Modules\SystemBase\app\Services\Base;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Base class for (nearly) every service.
 *
 * Provides:
 * - logging helpers (debug(), info(), warning(), error()) with a shared indent for nested processes
 * - an error bag filled by warning() and error(), see getErrors()
 */
class BaseService
{
    /**
     * Debug mode is set by default from config 'app.debug'.
     * debug() only writes to the log if this is true.
     *
     * @var bool
     */
    public bool $debugMode = false;

    /**
     * Messages collected by warning() and error().
     *
     * @var array
     */
    protected array $errorBag = [];

    /**
     * Key in the 'array' cache store holding the current log indent.
     * The 'array' store lives for the current request/command only and is shared by all services.
     *
     * @var string
     */
    protected string $cacheKey = 'global_base_service_indent';

    /**
     * Reads the debug mode from config.
     */
    public function __construct()
    {
        $this->debugMode = (bool) config('app.debug', false);
    }

    /**
     * All messages collected by warning() and error().
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errorBag;
    }

    /**
     * Current log indent (for this request/command).
     *
     * @return int
     */
    public function getIndentValue(): int
    {
        return Cache::store('array')->get($this->cacheKey, 0);
    }

    /**
     * Indents all following log messages by one more level.
     *
     * @return void
     */
    public function incrementIndent(): void
    {
        $v = $this->getIndentValue() + 1;
        Cache::store('array')->set($this->cacheKey, $v);
    }

    /**
     * Reduces the log indent by $count levels (never below 0).
     *
     * @param  int  $count
     *
     * @return void
     */
    public function decrementIndent(int $count = 1): void
    {
        Cache::store('array')->set($this->cacheKey, max(0, $this->getIndentValue() - $count));
    }

    /**
     * Two spaces per indent level.
     *
     * @return string
     */
    public function getIndentString(): string
    {
        return str_pad('', $this->getIndentValue() * 2);
    }

    /**
     * Logs a debug message, but only if $debugMode is true.
     * Non-scalar messages are logged by print_r().
     *
     * @param  mixed  $message
     * @param  array  $context
     *
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
     * Logs a warning and adds the message to the error bag.
     * Non-scalar messages are logged by print_r().
     *
     * @param  mixed  $message
     * @param  array  $context
     *
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
     * Logs an error and adds the message to the error bag.
     * Non-scalar messages are logged by print_r().
     *
     * @param  mixed  $message
     * @param  array  $context
     *
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
     * Logs an info message.
     * Non-scalar messages are logged by print_r().
     *
     * @param  mixed  $message
     * @param  array  $context
     *
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