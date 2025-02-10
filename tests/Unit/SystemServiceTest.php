<?php

namespace Modules\SystemBase\tests\Unit;


use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SystemBase\app\Services\Base\AddonObjectService;
use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\app\Services\ThemeService;
use Modules\SystemBase\tests\TestCase;
use Nwidart\Modules\Module;

class SystemServiceTest extends TestCase
{
    /**
     * Testing: toArray()
     *
     * @return void
     */
    public function testToArray()
    {
        $this->assertTrue(is_array(app('system_base')->toArray(1)));
        $this->assertTrue(is_array(app('system_base')->toArray("test")));
        $this->assertTrue((app('system_base')->toArray(new class {
                public int $meaningOfLife = 42;
            }))['meaningOfLife'] === 42);

        $testObject = new class {
            public array $meaningOfLife = [];

            public function __construct()
            {
                // create nested object
                $this->meaningOfLife['x'] = new class {
                    public int $y = 42;
                };
            }
        };
        $this->assertTrue((app('system_base')->toArray($testObject))['meaningOfLife']['x']['y'] === 42);
    }

    /**
     * Testing: toArrayRaw()
     *
     * @return void
     */
    public function testToArrayRaw()
    {
        $this->assertTrue(!is_array(app('system_base')->toArrayRaw(1)));
        $this->assertTrue(!is_array(app('system_base')->toArrayRaw("test")));
        $this->assertTrue((app('system_base')->toArrayRaw(new class {
                public int $meaningOfLife = 42;
            }))['meaningOfLife'] === 42);

        $testObject = new class {
            public array $meaningOfLife = [];

            public function __construct()
            {
                // create nested object
                $this->meaningOfLife['x'] = new class {
                    public int $y = 42;
                };
            }
        };
        // deep 1 was not converted nested object ...
        $this->assertTrue((app('system_base')->toArrayRaw($testObject, 1))['meaningOfLife']['x']->y === 42);
        // deep 5 converted nested object ...
        $this->assertTrue((app('system_base')->toArrayRaw($testObject, 5))['meaningOfLife']['x']['y'] === 42);
    }

    /**
     * Testing: arrayCompareIsEqual()
     *
     * @return void
     */
    public function testArrayCompareIsEqual()
    {
        $a = [1, 2, 9];
        $b = [1, 2, 9];
        $this->assertTrue(app('system_base')->arrayCompareIsEqual($a, $b));
        $a = [1, 2, ['a' => 'a1']];
        $b = [1, 2, ['a' => 'a1']];
        $this->assertTrue(app('system_base')->arrayCompareIsEqual($a, $b));
        $a = [1, 2, ['a' => 'a1']];
        $b = [1, 3, ['a' => 'a1']];
        $this->assertFalse(app('system_base')->arrayCompareIsEqual($a, $b));
        $a = [1, 2, ['a' => 'a1']];
        $b = [1, 2, ['a' => 'a2']];
        $this->assertFalse(app('system_base')->arrayCompareIsEqual($a, $b));
        $a = [1, 2, ['a' => 'a1']];
        $b = [1, 2, ['a' => 'a1', 'b' => null]];
        $this->assertFalse(app('system_base')->arrayCompareIsEqual($a, $b));
    }

    /**
     * Testing: arrayMergeRecursiveDistinct() and arrayCompareIsEqual()
     *
     * @return void
     */
    public function testArrayMergeRecursiveDistinctDefault()
    {
        $testObjects = [
            [
                'a'      => [],
                'b'      => [],
                'result' => [],
            ],
            [
                'a'      => [
                    'a' => [
                        'a1' => 'a1 Value',
                        'a2' => 'a2 Value',
                    ],
                    'b' => [
                        'b1' => 'b1 Value',
                        'b2' => 'b2 Value',
                    ],
                ],
                'b'      => [
                    'b' => [
                        'b1' => 'new b1 Value',
                        'b3' => 'b3 Value',
                    ],
                    'c' => [
                        'c1' => 'c1 Value',
                        'c2' => 'c2 Value',
                    ],
                ],
                'result' => [
                    'a' => [
                        'a1' => 'a1 Value',
                        'a2' => 'a2 Value',
                    ],
                    'b' => [
                        'b1' => 'new b1 Value',
                        'b2' => 'b2 Value',
                        'b3' => 'b3 Value',
                    ],
                    'c' => [
                        'c1' => 'c1 Value',
                        'c2' => 'c2 Value',
                    ],
                ],
            ],
        ];

        foreach ($testObjects as &$testObject) {
            $result = app('system_base')->arrayMergeRecursiveDistinct($testObject['a'], $testObject['b']);
            $this->assertTrue(app('system_base')->arrayCompareIsEqual($result, $testObject['result']), "Unexpected result.");
        }

        // test source was not changed
        $this->assertTrue(data_get($testObjects, '1.a.b.b1') === 'b1 Value', "Unexpected result.");
    }

    /**
     * Testing: arrayMergeRecursiveDistinct() and arrayCompareIsEqual()
     *
     * @return void
     */
    public function testArrayMergeRecursiveDistinctForceOverride()
    {
        $testObjects = [
            [
                'a'      => [
                    'a' => [
                        'a1' => 'a1 Value',
                        'a2' => 'a2 Value',
                    ],
                ],
                'b'      => [
                    'a' => [
                        'a1' => null,
                    ],
                ],
                'result' => [
                    'a' => [
                        'a1' => null,
                        'a2' => 'a2 Value',
                    ],
                ],
            ],
        ];

        foreach ($testObjects as $testObject) {
            $result = app('system_base')->arrayMergeRecursiveDistinct($testObject['a'], $testObject['b'], true);
            $this->assertTrue(app('system_base')->arrayCompareIsEqual($result, $testObject['result']),
                "Unexpected result.");
        }
    }

    /**
     * Testing: arrayRootCopyWhitelistedNoArrays()
     *
     * @return void
     */
    public function testArrayRootCopyWhitelistedNoArrays()
    {
        // copy disabled parent false
        $a = [
            'disabled'        => true,
            'meaning_of_life' => 42,
            'test'            => 9,
        ];
        $b = [
            'disabled'        => false,
            'meaning_of_life' => 42,
            'test'            => 9,
        ];
        $x = [
            'disabled'        => 'useless',
            'meaning_of_life' => 'useless',
            'test'            => 'useless',
        ];
        $this->assertFalse(app('system_base')->arrayRootCopyWhitelistedNoArrays($a, $b, $x)['disabled']);

        // disabled not present
        $b = [
            'meaning_of_life' => 42,
            'test'            => 9,
        ];
        $this->assertTrue(app('system_base')->arrayRootCopyWhitelistedNoArrays($a, $b, $x)['disabled']);

        // disabled present, but do not copy disabled
        $b = [
            'disabled'        => false,
            'meaning_of_life' => 42,
            'test'            => 9,
        ];
        $x = [
            'meaning_of_life' => 'useless',
            'test'            => 'useless',
        ];
        $this->assertTrue(app('system_base')->arrayRootCopyWhitelistedNoArrays($a, $b, $x)['disabled']);

    }

    /**
     * Testing: switchEnvDebug()
     *
     * @return void
     */
    public function testSwitchEnvDebug()
    {
        $prevValue = config('app.debug');

        app('system_base')->switchEnvDebug(true);
        $this->assertTrue(config('app.debug'));
        app('system_base')->switchEnvDebug(false);
        $this->assertFalse(config('app.debug'));
        app('system_base')->switchEnvDebug(true);
        $this->assertTrue(config('app.debug'));
        app('system_base')->switchEnvDebug(false);
        $this->assertFalse(config('app.debug'));

        app('system_base')->switchEnvDebug($prevValue);
    }

    /**
     * @return void
     */
    public function testHasInstanceClassOrTrait()
    {
        $this->assertTrue(app('system_base')->hasInstanceClassOrTrait(app(User::class), HasFactory::class));
        $this->assertTrue(app('system_base')->hasInstanceClassOrTrait(app(ThemeService::class),
            AddonObjectService::class));
    }

    /**
     * @return void
     */
    public function testHasData()
    {
        $testObjects = [
            'a' => [
                'a1' => 42,
                'a2' => null,
            ],
            'b' => [],
        ];

        $this->assertTrue(app('system_base')->hasData($testObjects, 'a.a1'));
        $this->assertTrue(app('system_base')->hasData($testObjects, 'a.a2'));
        $this->assertFalse(app('system_base')->hasData($testObjects, 'b.b1'));
    }

    /**
     * Find last user model by the module priority way and compare it to the "should be" inheritance
     *
     * @return void
     */
    public function testGetUserClassName()
    {
        $found = [
            'class'    => User::class,
            'priority' => 1,
        ];

        /** @var ModuleService $moduleService */
        $moduleService = app(ModuleService::class);
        $moduleService->runOrderedEnabledModules(function (?Module $module) use (&$found) {
            $class = 'Modules\\'.$module->getStudlyName().'\\app\\Models\\User';
            if (class_exists($class)) {
                if ((new $class) instanceof User) {
                    if ((int) $module->getPriority() > $found['priority']) {
                        $found['class'] = $class;
                        $found['priority'] = (int) $module->getPriority();
                    }
                }
            }

            return true;
        });

        $this->assertTrue(app('system_base')->getUserClassName() === $found['class']);
    }
}
