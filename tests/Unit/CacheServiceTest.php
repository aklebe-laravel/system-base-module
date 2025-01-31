<?php

namespace Modules\SystemBase\tests\Unit;

use Modules\SystemBase\app\Services\CacheService;
use Modules\SystemBase\tests\TestCase;

class CacheServiceTest extends TestCase
{
    /**
     * use cache ttl 10
     *
     * @return void
     */
    public function testCommonCache_10()
    {
        $cacheCounter = 4;
        for ($i = 0; $i < 10; $i++) {
            $result = app(CacheService::class)->remember('test_cache_service_'.__METHOD__, 10, function () use (&$cacheCounter) {
                return ++$cacheCounter;
            });
        }

        $this->assertTrue($result === 5);
    }

    /**
     * use cache ttl 10, but cache is disabled
     *
     * @return void
     */
    public function testCommonCache_10_butDisabled()
    {
        // disable cache
        app(CacheService::class)->disable();

        $cacheCounter = 4;
        for ($i = 0; $i < 10; $i++) {
            $result = app(CacheService::class)->remember('test_cache_service_'.__METHOD__, 10, function () use (&$cacheCounter) {
                return ++$cacheCounter;
            });
        }

        $this->assertTrue($result === 14);
    }

    /**
     * avoid cache by tll 0
     *
     * @return void
     */
    public function testCommonCache_0()
    {
        $cacheCounter = 4;
        for ($i = 0; $i < 10; $i++) {
            $result = app(CacheService::class)->remember('test_cache_service_'.__METHOD__, 0, function () use (&$cacheCounter) {
                $cacheCounter++;
                return $cacheCounter;
            });
        }

        $this->assertTrue($result === 14);
    }

    /**
     * avoid cache by tll 0
     *
     * @return void
     */
    public function testCommonCache_minus1()
    {
        $cacheCounter = 4;
        for ($i = 0; $i < 10; $i++) {
            $result = app(CacheService::class)->remember('test_cache_service_'.__METHOD__, -1, function () use (&$cacheCounter) {
                $cacheCounter++;
                return $cacheCounter;
            });
        }

        $this->assertTrue($result === 14);
    }

    /**
     * use config cache ttl
     *
     * @return void
     */
    public function testCommonCacheWithConfig()
    {
        $cacheCounter = 4;
        for ($i = 0; $i < 10; $i++) {
            $result = app(CacheService::class)->rememberUseConfig('test_cache_service_'.__METHOD__, 'system-base.cache.db.signature.ttl', function () use (&$cacheCounter) {
                $cacheCounter++;
                return $cacheCounter;
            });
        }

        $this->assertTrue($result === 5);
    }
}
