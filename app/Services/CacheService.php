<?php
declare(strict_types = 1);

namespace Modules\SystemBase\app\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * Wraps Illuminate\Support\Facades\Cache with the following differences:
 *
 * 1) If config 'system-base.cache.enabled' (env MODULE_SYSTEM_BASE_CACHE_ENABLED) is false, nothing is cached at all.
 * 2) ttl 0 (or negative) means: no cache, the callback is always called. This differs from the Laravel cache!
 * 3) ttl self::ttlForever caches with rememberForever().
 * 4) Tags are only used if given. Not every cache store supports tags (like 'file' or 'database').
 *
 * Notice: the cache is NOT separated by user! Add a user key to such content
 * or use rememberFrontendUserBased().
 */
class CacheService extends BaseService
{
    /**
     * Use this ttl to cache forever.
     */
    const int ttlForever = -9999999;

    /**
     * Disables the cache for the rest of this request/command (changes the runtime config).
     *
     * @return void
     */
    public function disable(): void
    {
        config(['system-base.cache.enabled' => false]);
    }

    /**
     * Enables the cache for the rest of this request/command (changes the runtime config).
     *
     * @return void
     */
    public function enable(): void
    {
        config(['system-base.cache.enabled' => true]);
    }

    /**
     * True if caching is enabled by config.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) config('system-base.cache.enabled', true);
    }

    /**
     * Returns the cached value or calls $callback and caches its result.
     *
     * @param  string   $key  cache key
     * @param  int      $ttl  ttl in seconds, <= 0: no caching, self::ttlForever: forever
     * @param  Closure  $callback
     * @param  array    $tags  only for stores supporting tags
     *
     * @return mixed
     */
    public function remember(string $key, int $ttl, Closure $callback, array $tags = []): mixed
    {
        if ($ttl === self::ttlForever) {
            return $this->rememberForever($key, $callback, $tags);
        }

        if (($ttl <= 0) || !$this->isEnabled()) {
            return $callback();
        }

        return $this->getStore($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Returns the cached value or calls $callback and caches its result forever.
     *
     * @param  string   $key
     * @param  Closure  $callback
     * @param  array    $tags  only for stores supporting tags
     *
     * @return mixed
     */
    public function rememberForever(string $key, Closure $callback, array $tags = []): mixed
    {
        if (!$this->isEnabled()) {
            return $callback();
        }

        return $this->getStore($tags)->rememberForever($key, $callback);
    }

    /**
     * Like remember(), but the ttl is read from config.
     *
     * @param  string   $key                  cache key
     * @param  string   $configKEyForTtl      config path to the ttl like 'system-base.cache.frontend.ttl'
     * @param  Closure  $callback
     * @param  bool     $useConfigDefaultTtl  use 'system-base.cache.default_ttl' if the config path is missing
     *
     * @return mixed
     */
    public function rememberUseConfig(string $key, string $configKEyForTtl, Closure $callback, bool $useConfigDefaultTtl = true): mixed
    {
        $ttlDefault = $useConfigDefaultTtl ? (int) config('system-base.cache.default_ttl', 0) : 0;
        $ttl = (int) config($configKEyForTtl, $ttlDefault);

        return $this->remember($key, $ttl, $callback);
    }

    /**
     * Cache for frontend content (config 'system-base.cache.frontend.ttl').
     * Notice: NOT separated by user! Use rememberFrontendUserBased() for user specific content.
     *
     * @param  string   $key  cache key
     * @param  Closure  $callback
     *
     * @return mixed
     */
    public function rememberFrontend(string $key, Closure $callback): mixed
    {
        return $this->rememberUseConfig($key, 'system-base.cache.frontend.ttl', $callback);
    }

    /**
     * Cache for user specific frontend content (config 'system-base.cache.user_based_frontend.ttl').
     * The current user id (or 0 for guests) is added to the key.
     *
     * @param  string   $key  cache key
     * @param  Closure  $callback
     *
     * @return mixed
     */
    public function rememberFrontendUserBased(string $key, Closure $callback): mixed
    {
        $userKey = app('website_base_settings')?->currentUser()?->getKey() ?? 0;
        $key .= '-'.$userKey;

        return $this->rememberUseConfig($key, 'system-base.cache.user_based_frontend.ttl', $callback);
    }

    /**
     * Removes a key from the default store (without tags).
     *
     * @param  string  $key
     *
     * @return bool
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * Reads a dot path inside a cached array. Null values return $default too.
     *
     * @param  string      $cacheRootKey
     * @param  string      $arrayPath
     * @param  mixed|null  $default
     *
     * @return mixed
     */
    public function getCacheByPath(string $cacheRootKey, string $arrayPath, mixed $default = null): mixed
    {
        return data_get(Cache::get($cacheRootKey, []), $arrayPath) ?? $default;
    }

    /**
     * Writes a dot path inside a cached array (forever, independent of the config switch).
     * Not atomic: parallel writers can overwrite each other.
     *
     * @param  string  $cacheRootKey
     * @param  string  $arrayPath
     * @param  mixed   $data
     *
     * @return void
     */
    public function setCacheByPath(string $cacheRootKey, string $arrayPath, mixed $data): void
    {
        $cache = Cache::get($cacheRootKey, []);
        data_set($cache, $arrayPath, $data);
        Cache::set($cacheRootKey, $cache);
    }

    /**
     * Tagged store if tags are given, otherwise the default store.
     * Calling Cache::tags() would throw on stores without tag support, even with an empty list.
     *
     * @param  array  $tags
     *
     * @return Repository
     */
    protected function getStore(array $tags = []): Repository
    {
        return $tags ? Cache::tags($tags) : Cache::store();
    }

}