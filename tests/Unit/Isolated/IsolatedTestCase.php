<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Modules\SystemBase\app\Services\FileService;
use Modules\SystemBase\app\Services\SystemService;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Base for unit tests WITHOUT booting the Laravel application (no database, no HTTP, no modules).
 *
 * A minimal container is created for every test, so helpers like config(), app(), __()
 * and the facades Log and Cache work:
 * - 'config': a plain config repository, see defaultConfig() and setConfig()
 * - 'translator': returns the key unchanged
 * - 'log': collects messages in $this->logMessages
 * - 'cache': array stores only
 * - 'system_base' and 'system_base_file' as singletons
 *
 * The previous container (like a booted app of other tests in the same run) is restored afterward.
 */
abstract class IsolatedTestCase extends TestCase
{
    /**
     * @var Container
     */
    protected Container $container;

    /**
     * Log entries as [level, message, context].
     *
     * @var array<int, array{0: string, 1: string, 2: array}>
     */
    public array $logMessages = [];

    /**
     * @var Container|null
     */
    private ?Container $previousContainer = null;

    /**
     * @var mixed
     */
    private mixed $previousFacadeApplication = null;

    /**
     * Temp directories created by makeTempDir(), deleted in tearDown().
     *
     * @var array<int, string>
     */
    private array $tempDirs = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();

        $this->container = new Container();
        Container::setInstance($this->container);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);

        $this->container->instance('config', new ConfigRepository($this->defaultConfig()));
        $this->container->instance('translator', new class {
            public function get($key, array $replace = [], $locale = null, $fallback = true)
            {
                return $key;
            }
        });
        $this->container->instance('log', $this->createLogger());
        $this->container->instance('cache', new class {
            /**
             * @var array<string, CacheRepository>
             */
            private array $stores = [];

            public function store($name = null): CacheRepository
            {
                return $this->stores[$name ?? 'default'] ??= new CacheRepository(new ArrayStore());
            }

            public function __call(string $method, array $parameters)
            {
                return $this->store()->$method(...$parameters);
            }
        });

        $this->container->singleton(SystemService::class);
        $this->container->alias(SystemService::class, 'system_base');
        $this->container->singleton(FileService::class);
        $this->container->alias(FileService::class, 'system_base_file');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->deleteDirectory($dir);
        }
        $this->tempDirs = [];

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);

        parent::tearDown();
    }

    /**
     * Config used for every test. Overwrite or use setConfig() to change it.
     *
     * @return array
     */
    protected function defaultConfig(): array
    {
        return [
            'app'         => [
                'debug' => false,
                'env'   => 'testing',
            ],
            'system-base' => [
                'cache' => [
                    'enabled'     => true,
                    'default_ttl' => 0,
                ],
            ],
        ];
    }

    /**
     * @param  string  $key
     * @param  mixed   $value
     *
     * @return void
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $this->container->make('config')->set($key, $value);
    }

    /**
     * Creates an empty temp directory which is deleted after the test.
     *
     * @return string
     */
    protected function makeTempDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('system-base-test-', true);
        mkdir($dir, 0775, true);
        $this->tempDirs[] = $dir;

        return $dir;
    }

    /**
     * Creates a file (and its directories) with content.
     *
     * @param  string  $path
     * @param  string  $content
     *
     * @return string the path
     */
    protected function makeFile(string $path, string $content = ''): string
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Messages logged with the given level.
     *
     * @param  string  $level
     *
     * @return array<int, string>
     */
    protected function getLoggedMessages(string $level): array
    {
        return array_values(array_map(fn(array $entry) => $entry[1],
            array_filter($this->logMessages, fn(array $entry) => $entry[0] === $level)));
    }

    /**
     * @return AbstractLogger
     */
    private function createLogger(): AbstractLogger
    {
        $test = $this;

        return new class($test) extends AbstractLogger {
            public function __construct(private readonly IsolatedTestCase $test)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->test->logMessages[] = [(string) $level, (string) $message, $context];
            }
        };
    }

    /**
     * @param  string  $dir
     *
     * @return void
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
