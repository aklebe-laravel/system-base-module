<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Feature;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\CacheService;
use Modules\SystemBase\tests\TestCase;

/**
 * CacheService with the configured cache store (redis in Sail and on PROD).
 * The logic is tested with array stores in tests/Unit/Isolated/CacheServiceTest, this one catches store differences.
 */
class CacheStoreTest extends TestCase
{
    /**
     * @var CacheService
     */
    protected CacheService $cache;

    /**
     * Unique per test run, so parallel or previous runs don't interfere.
     *
     * @var string
     */
    protected string $key;

    /**
     * @var int
     */
    protected int $calls = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->cache = app(CacheService::class);
        $this->cache->enable();
        $this->key = 'system-base-test-'.Str::random(12);
    }

    public function tearDown(): void
    {
        Cache::forget($this->key);
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags(['system-base-test'])
                ->forget($this->key);
        }

        parent::tearDown();
    }

    public function test_remember_and_forget(): void
    {
        $this->cache->remember($this->key, 60, fn() => ++$this->calls);
        $this->cache->remember($this->key, 60, fn() => ++$this->calls);
        $this->assertSame(1, $this->calls);

        $this->cache->forget($this->key);
        $this->cache->remember($this->key, 60, fn() => ++$this->calls);
        $this->assertSame(2, $this->calls);
    }

    /**
     * Regression: tagged values on redis were not forgotten without their tags (stale extra attributes).
     */
    public function test_tagged_remember_and_forget(): void
    {
        if (!(Cache::getStore() instanceof TaggableStore)) {
            $this->markTestSkipped('The configured cache store does not support tags.');
        }

        $this->cache->remember($this->key, 60, fn() => ++$this->calls, ['system-base-test']);
        $this->cache->remember($this->key, 60, fn() => ++$this->calls, ['system-base-test']);
        $this->assertSame(1, $this->calls);

        $this->assertTrue($this->cache->forget($this->key, ['system-base-test']));
        $this->cache->remember($this->key, 60, fn() => ++$this->calls, ['system-base-test']);
        $this->assertSame(2, $this->calls);
    }
}
