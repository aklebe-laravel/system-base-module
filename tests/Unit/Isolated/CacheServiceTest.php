<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\CacheService;

class CacheServiceTest extends IsolatedTestCase
{
    /**
     * @var CacheService
     */
    protected CacheService $cache;

    /**
     * @var int
     */
    protected int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = app(CacheService::class);
        $this->calls = 0;
    }

    /**
     * Calls remember() $times and returns the last result.
     */
    protected function rememberTimes(int $times, int $ttl, array $tags = []): int
    {
        $result = 0;
        for ($i = 0; $i < $times; $i++) {
            $result = $this->cache->remember('key', $ttl, fn() => ++$this->calls, $tags);
        }

        return $result;
    }

    public function test_positive_ttl_caches(): void
    {
        $this->assertSame(1, $this->rememberTimes(5, 10));
        $this->assertSame(1, $this->calls);
    }

    public function test_zero_and_negative_ttl_never_cache(): void
    {
        $this->assertSame(5, $this->rememberTimes(5, 0));
        $this->assertSame(10, $this->rememberTimes(5, -1));
    }

    public function test_forever_ttl_caches(): void
    {
        $this->assertSame(1, $this->rememberTimes(5, CacheService::ttlForever));
    }

    public function test_disabled_cache_never_caches(): void
    {
        $this->cache->disable();
        $this->assertFalse($this->cache->isEnabled());
        $this->assertSame(5, $this->rememberTimes(5, 10));
        $this->assertSame(10, $this->rememberTimes(5, CacheService::ttlForever));

        $this->cache->enable();
        $this->assertTrue($this->cache->isEnabled());
    }

    public function test_tags_are_supported(): void
    {
        $this->assertSame(1, $this->rememberTimes(3, 10, ['a-tag']));
    }

    public function test_rememberUseConfig_reads_ttl_from_config(): void
    {
        $this->setConfig('system-base.test.ttl', 10);
        for ($i = 0; $i < 3; $i++) {
            $this->cache->rememberUseConfig('cfg', 'system-base.test.ttl', fn() => ++$this->calls);
        }
        $this->assertSame(1, $this->calls);

        // missing config path and default_ttl 0: no caching
        for ($i = 0; $i < 3; $i++) {
            $this->cache->rememberUseConfig('cfg2', 'system-base.missing.ttl', fn() => ++$this->calls);
        }
        $this->assertSame(4, $this->calls);
    }

    public function test_cache_by_path(): void
    {
        $this->cache->setCacheByPath('root', 'a.b', 42);
        $this->cache->setCacheByPath('root', 'a.c', null);

        $this->assertSame(42, $this->cache->getCacheByPath('root', 'a.b'));
        $this->assertSame('default', $this->cache->getCacheByPath('root', 'a.c', 'default'));
        $this->assertSame('default', $this->cache->getCacheByPath('root', 'x.y', 'default'));
    }
}
