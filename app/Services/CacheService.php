<?php

namespace Modules\SystemBase\app\Services;


use Closure;
use Illuminate\Support\Facades\Cache;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * This Cache Service will catch and wrap the Illuminate\Support\Facades\Cache,
 * but with following changes:
 *
 * 1) if env CACHE_ENABLED is false (config('system-base.cache.enabled')) then nothing will be cached at all
 * 2) ttl: 0 means no cache is used
 * 3) ttl: ttlForever will cache with rememberForever()
 */
class CacheService extends BaseService
{
    /**
     *
     */
    const int ttlForever = -9999999;

    /**
     * @param  string   $key  cache  key
     * @param  int      $ttl  ttl in seconds
     * @param  Closure  $callback
     *
     * @return mixed
     */
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        if (!$ttl || !config('system-base.cache.enabled', true)) {
            return $callback();
        }

        // forever ?
        if ($ttl === self::ttlForever) {
            return $this->rememberForever($key, $callback);
        }

        //standard behaviour
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * @param  string   $key
     * @param  Closure  $callback
     *
     * @return mixed
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        if (!config('system-base.cache.enabled', true)) {
            return $callback();
        }

        return Cache::rememberForever($key, $callback);
    }

    /**
     * @param  string   $key                  cache key
     * @param  string   $configKEyForTtl      config path to ttl
     * @param  Closure  $callback
     * @param  bool     $useConfigDefaultTtl  always use default ttl if no ttl given.
     *
     * @return mixed
     */
    public function rememberUseConfig(string $key, string $configKEyForTtl, Closure $callback, bool $useConfigDefaultTtl = true): mixed
    {
        $ttlDefault = $useConfigDefaultTtl ? (int) config('system-base.cache.default_ttl', 0) : 0;
        $ttl = (int) config($configKEyForTtl, $ttlDefault);

        return $this->remember($key, $ttl, $callback);
    }
}