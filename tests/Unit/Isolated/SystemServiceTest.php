<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\SystemService;
use PHPUnit\Framework\Attributes\DataProvider;

class SystemServiceTest extends IsolatedTestCase
{
    /**
     * @var SystemService
     */
    protected SystemService $service;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app('system_base');
    }

    public function test_constructor_builds_special_select_options(): void
    {
        $this->assertSame(['_none_' => '[None]'], $this->service->selectOptionsSimple[SystemService::selectValueNone]);
        $this->assertSame(['_all_' => ['label' => '[All]']], $this->service->selectOptionsCompact[SystemService::selectValueAll]);
    }

    public function test_toArray_wraps_scalars_and_converts_objects_deeply(): void
    {
        $this->assertSame([1], $this->service->toArray(1));
        $this->assertSame([null], $this->service->toArray(null));
        $this->assertSame(['a' => 1], $this->service->toArray(['a' => 1]));

        $object = new class {
            public array $list = [];

            public function __construct()
            {
                $this->list['x'] = new class {
                    public int $y = 42;
                };
            }
        };
        $this->assertSame(42, $this->service->toArray($object)['list']['x']['y']);
    }

    public function test_toArrayRaw_respects_max_deep(): void
    {
        $object = new class {
            public array $list = [];

            public function __construct()
            {
                $this->list['x'] = new class {
                    public int $y = 42;
                };
            }
        };

        $this->assertSame(1, $this->service->toArrayRaw(1));
        $this->assertIsObject($this->service->toArrayRaw($object, 1)['list']['x']);
        $this->assertSame(42, $this->service->toArrayRaw($object, 5)['list']['x']['y']);
    }

    public function test_arrayMergeRecursiveDistinct_merges_nested_and_ignores_null_by_default(): void
    {
        $destination = ['a' => ['a1' => 1, 'a2' => 2], 'b' => 'keep'];
        $source = ['a' => ['a1' => 10, 'a3' => 3], 'b' => null, 'c' => 'new'];

        $this->assertSame(
            ['a' => ['a1' => 10, 'a2' => 2, 'a3' => 3], 'b' => 'keep', 'c' => 'new'],
            $this->service->arrayMergeRecursiveDistinct($destination, $source)
        );
    }

    public function test_arrayMergeRecursiveDistinct_force_override_also_writes_null(): void
    {
        $result = $this->service->arrayMergeRecursiveDistinct(['a' => ['a1' => 1]], ['a' => ['a1' => null]], true);

        $this->assertSame(['a' => ['a1' => null]], $result);
    }

    public function test_arrayMergeRecursiveDistinct_passes_options_to_nested_levels(): void
    {
        // $ignoreNull = false has to work on nested levels too
        $result = $this->service->arrayMergeRecursiveDistinct(['a' => ['a1' => 1]], ['a' => ['a1' => null]], false, false);
        $this->assertSame(['a' => ['a1' => null]], $result);

        // $butForceOverrideKeys has to work on nested levels too
        $result = $this->service->arrayMergeRecursiveDistinct(['a' => ['a1' => 1, 'a2' => 2]], ['a' => ['a1' => null, 'a2' => null]], false, true, ['a1']);
        $this->assertSame(['a' => ['a1' => null, 'a2' => 2]], $result);
    }

    public function test_arrayCompareIsEqual(): void
    {
        $a = ['x' => 1, 'y' => ['z' => 'abc']];
        $sameDifferentOrder = ['y' => ['z' => 'abc'], 'x' => 1];
        $differentType = ['x' => '1', 'y' => ['z' => 'abc']];
        $moreKeys = ['x' => 1, 'y' => ['z' => 'abc'], 'q' => null];
        $dotKey = ['a.b' => 1];
        $dotKeyCopy = ['a.b' => 1];
        $nestedVsScalar = ['y' => 'abc'];
        $nested = ['y' => ['abc']];

        $this->assertTrue($this->service->arrayCompareIsEqual($a, $sameDifferentOrder));
        $this->assertFalse($this->service->arrayCompareIsEqual($a, $differentType));
        $this->assertFalse($this->service->arrayCompareIsEqual($a, $moreKeys));
        $this->assertTrue($this->service->arrayCompareIsEqual($dotKey, $dotKeyCopy));
        $this->assertFalse($this->service->arrayCompareIsEqual($nested, $nestedVsScalar));
    }

    public function test_arrayRootCopyWhitelistedNoArrays(): void
    {
        $dest = ['disabled' => true, 'x' => 1];
        $src = ['disabled' => false, 'x' => 2, 'list' => [1]];

        $this->assertSame(['disabled' => false, 'x' => 1],
            $this->service->arrayRootCopyWhitelistedNoArrays($dest, $src, ['disabled' => '', 'list' => '']));
    }

    public function test_toHtmlSelectOptions_by_properties(): void
    {
        $list = [
            ['id' => 3, 'name' => 'Charlie', 'type' => 'c'],
            ['id' => 1, 'name' => 'alpha', 'type' => 'a'],
        ];

        $this->assertSame([3 => 'Charlie', 1 => 'alpha'], $this->service->toHtmlSelectOptions($list, 'name', 'id'));
        $this->assertSame([3 => 'Charlie | c', 1 => 'alpha | a'], $this->service->toHtmlSelectOptions($list, ['name', 'type'], 'id'));
        $this->assertSame([3 => 'C', 1 => 'A'], $this->service->toHtmlSelectOptions($list, fn($item) => strtoupper($item['type']), 'id'));
        $this->assertSame(['a' => 'a', 'b' => 'b'], $this->service->toHtmlSelectOptions(['a', 'b']));
        $this->assertSame(['x' => 'x', 'y' => 'y'], $this->service->toHtmlSelectOptions(['x' => 'a', 'y' => 'b'], '[key]'));
        $this->assertSame(['' => '-', 3 => 'Charlie', 1 => 'alpha'], $this->service->toHtmlSelectOptions($list, 'name', 'id', ['' => '-']));
    }

    public function test_toHtmlSelectOptions_sort_keeps_keys(): void
    {
        $list = [
            ['id' => 3, 'name' => 'charlie'],
            ['id' => 1, 'name' => 'Bravo'],
            ['id' => 2, 'name' => 'alpha'],
        ];

        $byValueAsc = $this->service->toHtmlSelectOptions($list, 'name', 'id', [], SystemService::SortModeByValue | SystemService::SortModeAsc);
        $this->assertSame([2 => 'alpha', 1 => 'Bravo', 3 => 'charlie'], $byValueAsc);

        $byValueDesc = $this->service->toHtmlSelectOptions($list, 'name', 'id', [], SystemService::SortModeByValue | SystemService::SortModeDesc);
        $this->assertSame([3 => 'charlie', 1 => 'Bravo', 2 => 'alpha'], $byValueDesc);

        $byKeyAsc = $this->service->toHtmlSelectOptions($list, 'name', 'id', [], SystemService::SortModeByKey | SystemService::SortModeAsc);
        $this->assertSame([1 => 'Bravo', 2 => 'alpha', 3 => 'charlie'], $byKeyAsc);

        // the "first" options stay in front
        $withFirst = $this->service->toHtmlSelectOptions($list, 'name', 'id', ['' => '[none]'], SystemService::SortModeByValue | SystemService::SortModeAsc);
        $this->assertSame(['', 2, 1, 3], array_keys($withFirst));
    }

    public function test_isCallableClosure_rejects_function_names(): void
    {
        $this->assertTrue($this->service->isCallableClosure(fn() => 1));
        $this->assertTrue($this->service->isCallableClosure([SystemService::class, 'toArrayPreserveKeys']));
        $this->assertFalse($this->service->isCallableClosure('max'));
        $this->assertFalse($this->service->isCallableClosure(null));
    }

    public function test_getCallable(): void
    {
        $closure = fn() => 1;
        $this->assertSame($closure, $this->service->getCallable($closure));
        $this->assertNull($this->service->getCallable('max'));

        // [class, method] which is not static: the class is resolved by the container
        $callable = $this->service->getCallable([SystemService::class, 'getSimpleClassName']);
        $this->assertSame('Foo', $callable('Bar\\Foo'));
    }

    public function test_getSimpleClassName(): void
    {
        $this->assertSame('SystemService', $this->service->getSimpleClassName(SystemService::class));
        $this->assertSame('', $this->service->getSimpleClassName('NoNamespace'));
    }

    public function test_strCaseCompare(): void
    {
        $this->assertTrue($this->service->strCaseCompare('de', 'DE'));
        $this->assertTrue($this->service->strCaseCompare('über', 'ÜBER'));
        $this->assertFalse($this->service->strCaseCompare('a', 'b'));
    }

    public function test_isInRegexList_and_removeBlacklistItems(): void
    {
        $this->assertTrue($this->service->isInRegexList('file.php', ['/\.txt$/', '/\.php$/']));
        $this->assertTrue($this->service->isInRegexList('file.php', ['\.php$'], '#'));
        $this->assertFalse($this->service->isInRegexList('file.php', ['\.txt$'], '#'));

        $this->assertSame(['a.php', 'c.php'], $this->service->removeBlacklistItems(['a.php', 'b.txt', 'c.php'], ['/\.txt$/']));
    }

    public static function bytesProvider(): array
    {
        return [
            [0, '0 B'],
            [1023, '1023 B'],
            [1024, '1 KiB'],
            [1536, '1.5 KiB'],
            [1024 * 1024, '1 MiB'],
            [1024 ** 6, '1024 PiB'],
        ];
    }

    #[DataProvider('bytesProvider')]
    public function test_bytesToHuman(int|float $bytes, string $expected): void
    {
        $this->assertSame($expected, $this->service->bytesToHuman($bytes));
    }

    public function test_env_groups(): void
    {
        $this->assertTrue($this->service->isEnvGroupTesting());
        $this->assertFalse($this->service->isEnvGroupProduction());
        $this->assertTrue($this->service->isEnvGroupProduction('prod'));
        $this->assertTrue($this->service->isEnvGroupImportant('staging'));
        $this->assertFalse($this->service->isEnvGroupProduction('staging'));
        $this->assertTrue($this->service->isEnvGroupDevelopment('local'));
        $this->assertFalse($this->service->isInEnvGroup('unknown-group', 'local'));
    }

    public function test_runThroughArray_reports_paths_and_depth(): void
    {
        $scalars = [];
        $nodes = [];
        $this->service->runThroughArray(['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => null]]],
            function ($key, $value, $root, $deep) use (&$scalars) {
                $scalars[] = [$root, $key, $value, $deep];
            }, '', 0,
            function ($key, $value, $root, $deep) use (&$nodes) {
                $nodes[] = [$root, $key, $deep];
            });

        $this->assertSame([['', 'a', 1, 1], ['b', 'c', 2, 2], ['b.d', 'e', null, 3]], $scalars);
        $this->assertSame([['', 'b', 1], ['b', 'd', 2]], $nodes);
    }

    public function test_assignArrayKeysByValue(): void
    {
        $this->assertSame(['a' => 'a', 'b' => 'b'], $this->service->assignArrayKeysByValue(['a', 'b']));
        $this->assertSame([], $this->service->assignArrayKeysByValue(null));
    }

    public function test_setArrayValueIfChanged(): void
    {
        $target = ['a' => ['b' => 1]];

        $this->assertFalse($this->service->setArrayValueIfChanged($target, 'a.b', 1));
        $this->assertTrue($this->service->setArrayValueIfChanged($target, 'a.b', 2));
        $this->assertTrue($this->service->setArrayValueIfChanged($target, 'a.c', null));
        $this->assertSame(['a' => ['b' => 2, 'c' => null]], $target);

        $this->assertFalse($this->service->setArrayValueIfChanged($target, '', ['a' => ['b' => 2, 'c' => null]]));
        $this->assertTrue($this->service->setArrayValueIfChanged($target, '', []));
        $this->assertSame([], $target);
    }

    public function test_hasData_and_hasInstanceClassOrTrait(): void
    {
        $data = ['a' => ['a1' => 42, 'a2' => null]];

        $this->assertTrue($this->service->hasData($data, 'a.a1'));
        $this->assertTrue($this->service->hasData($data, 'a.a2'));
        $this->assertFalse($this->service->hasData($data, 'a.a3'));

        $withTrait = new class {
            use \Illuminate\Support\Traits\Macroable;
        };
        $this->assertTrue($this->service->hasInstanceClassOrTrait($withTrait, \Illuminate\Support\Traits\Macroable::class));
        $this->assertTrue($this->service->hasInstanceClassOrTrait($this->service, \Modules\SystemBase\app\Services\Base\BaseService::class));
        $this->assertFalse($this->service->hasInstanceClassOrTrait('string', \Illuminate\Support\Traits\Macroable::class));
    }

    public function test_unique_counters(): void
    {
        $this->assertSame(0, $this->service->getUniqueCounter('x'));
        $this->assertSame(1, $this->service->addUniqueCounter('x'));
        $this->assertSame(2, $this->service->addUniqueCounter('x'));
        $this->assertSame(1, $this->service->addUniqueCounter('y'));
        $this->assertSame(2, $this->service->getUniqueCounter('x'));
    }

    public function test_getShortSecHash(): void
    {
        $hash = $this->service->getShortSecHash('abc');

        $this->assertSame(12, strlen($hash));
        $this->assertSame($hash, $this->service->getShortSecHash('abc'));
        $this->assertNotSame($hash, $this->service->getShortSecHash('abd'));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $this->service->getShortSecHash('abc', 40));
        $this->assertSame(20, strlen($this->service->getShortSecHash(null, 20)));
    }

    public function test_parseEnv_replaces_known_vars_only(): void
    {
        putenv('SYSTEM_BASE_TEST_DOMAIN=example.test');
        $_ENV['SYSTEM_BASE_TEST_DOMAIN'] = 'example.test';
        $_SERVER['SYSTEM_BASE_TEST_DOMAIN'] = 'example.test';

        try {
            $subject = 'https://${SYSTEM_BASE_TEST_DOMAIN}/${SYSTEM_BASE_TEST_UNKNOWN}/${{placeholder}}';
            $this->assertSame('https://example.test/${SYSTEM_BASE_TEST_UNKNOWN}/${{placeholder}}', $this->service->parseEnv($subject));

            $this->assertSame(['a' => ['b' => 'example.test'], 'c' => 1],
                $this->service->parseEnvArray(['a' => ['b' => '${SYSTEM_BASE_TEST_DOMAIN}'], 'c' => 1]));
        } finally {
            putenv('SYSTEM_BASE_TEST_DOMAIN');
            unset($_ENV['SYSTEM_BASE_TEST_DOMAIN'], $_SERVER['SYSTEM_BASE_TEST_DOMAIN']);
        }
    }

    public function test_toArrayPreserveKeys_keeps_key_case(): void
    {
        $object = new \stdClass();
        $object->CamelCase = ['innerKey' => new \ArrayObject(['x' => 1])];

        $result = SystemService::toArrayPreserveKeys(['root' => $object]);

        $this->assertArrayHasKey('CamelCase', $result['root']);
        $this->assertArrayHasKey('innerKey', $result['root']['CamelCase']);
    }

    public function test_getExecutionTime_works_without_LARAVEL_START(): void
    {
        $start = microtime(true) - 2;

        $this->assertGreaterThanOrEqual(2.0, $this->service->getExecutionTime($start));
        $this->assertGreaterThanOrEqual(0.0, $this->service->getExecutionTime());
    }
}
