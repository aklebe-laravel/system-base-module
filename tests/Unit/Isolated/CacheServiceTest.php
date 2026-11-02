<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Illuminate\Cache\FileStore;
use Illuminate\Cache\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
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

    public function test_rememberForever_respects_tags_and_disabled_cache(): void
    {
        $this->assertSame(1, $this->cache->rememberForever('forever', fn() => ++$this->calls, ['a-tag']));
        $this->assertSame(1, $this->cache->rememberForever('forever', fn() => ++$this->calls, ['a-tag']));

        $this->cache->disable();
        $this->assertSame(2, $this->cache->rememberForever('forever', fn() => ++$this->calls, ['a-tag']));
    }

    public function test_tagged_and_untagged_values_are_separated(): void
    {
        $this->cache->remember('same-key', 10, fn() => 'untagged');
        $this->cache->remember('same-key', 10, fn() => 'tagged', ['a-tag']);

        $this->assertSame('untagged', $this->cache->remember('same-key', 10, fn() => 'new'));
        $this->assertSame('tagged', $this->cache->remember('same-key', 10, fn() => 'new', ['a-tag']));
    }

    public function test_forget_without_tags_removes_untagged_value(): void
    {
        $this->rememberTimes(1, 10);

        $this->assertTrue($this->cache->forget('key'));
        $this->assertFalse($this->cache->forget('key'));
        $this->assertSame(2, $this->rememberTimes(1, 10));
    }

    /**
     * Regression: a value cached with tags must be forgotten with the same tags,
     * otherwise a taggable store (array, redis) keeps it until the ttl expires.
     */
    public function test_forget_with_tags_removes_tagged_value(): void
    {
        $this->rememberTimes(1, 10, ['a-tag']);

        $this->cache->forget('key');
        $this->assertSame(1, $this->rememberTimes(1, 10, ['a-tag']), 'Without tags the tagged value stays.');

        $this->assertTrue($this->cache->forget('key', ['a-tag']));
        $this->assertSame(2, $this->rememberTimes(1, 10, ['a-tag']));
    }

    public function test_forget_with_tags_removes_untagged_value_too(): void
    {
        $this->rememberTimes(1, 10);

        $this->assertTrue($this->cache->forget('key', ['a-tag']));
        $this->assertSame(2, $this->rememberTimes(1, 10));
    }

    public function test_forget_with_tags_on_store_without_tag_support(): void
    {
        $this->container->instance('cache',
            new class($this->makeTempDir()) {
                private Repository $store;

                public function __construct(string $dir)
                {
                    $this->store = new Repository(new FileStore(new Filesystem(), $dir));
                }

                public function __call(string $method, array $parameters)
                {
                    return $this->store->$method(...$parameters);
                }
            });
        Cache::clearResolvedInstances();

        Cache::put('file-key', 'value', 10);

        $this->assertTrue($this->cache->forget('file-key', ['a-tag']));
        $this->assertNull(Cache::get('file-key'));
    }

    public function test_rememberFrontend_uses_frontend_ttl(): void
    {
        $this->setConfig('system-base.cache.frontend.ttl', 10);

        $this->cache->rememberFrontend('frontend', fn() => ++$this->calls);
        $this->cache->rememberFrontend('frontend', fn() => ++$this->calls);

        $this->assertSame(1, $this->calls);
    }

    public function test_rememberFrontendUserBased_separates_users(): void
    {
        $this->setConfig('system-base.cache.user_based_frontend.ttl', 10);
        $settings = new class {
            public ?object $user = null;

            public function currentUser(): ?object
            {
                return $this->user;
            }
        };
        $this->container->instance('website_base_settings', $settings);

        $guest = $this->cache->rememberFrontendUserBased('content', fn() => 'guest '.++$this->calls);

        $settings->user = new class {
            public function getKey(): int
            {
                return 5;
            }
        };
        $user5 = $this->cache->rememberFrontendUserBased('content', fn() => 'user '.++$this->calls);
        $user5Again = $this->cache->rememberFrontendUserBased('content', fn() => 'user '.++$this->calls);

        $this->assertSame('guest 1', $guest);
        $this->assertSame('user 2', $user5);
        $this->assertSame('user 2', $user5Again);
        $this->assertSame('guest 1', Cache::get('content-0'));
        $this->assertSame('user 2', Cache::get('content-5'));
    }
}
