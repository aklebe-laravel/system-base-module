<?php

namespace Modules\SystemBase\tests\Feature;

use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\tests\TestCase;

class ModuleTest extends TestCase
{
    /**
     * Testing results of ModuleService::getItemInfo()
     */
    public function test_module_item_info_results(): void
    {
        $testList = [
            [
                'params' => 'TestWith-chaotic_caseEx',
                'result' => [
                    'studly_name'           => 'TestWithChaoticCaseEx',
                    'snake_name'            => 'test-with-chaotic-case-ex',
                    'vendor_name'           => env('MODULE_DEPLOYENV_REQUIRE_MODULES_DEFAULT_VENDOR'),
                    'module_snake_name'     => 'test-with-chaotic-case-ex',
                    'module_snake_name_git' => 'test-with-chaotic-case-ex-module',

                ],
            ],
            [
                'params' => 'xyz_abc-a123',
                'result' => [
                    'studly_name'           => 'XyzAbcA123',
                    'snake_name'            => 'xyz-abc-a123',
                    'vendor_name'           => env('MODULE_DEPLOYENV_REQUIRE_MODULES_DEFAULT_VENDOR'),
                    'module_snake_name'     => 'xyz-abc-a123',
                    'module_snake_name_git' => 'xyz-abc-a123-module',

                ],
            ],
            [
                'params' => 'xyz_abc-a123-module',
                'result' => [
                    'studly_name'           => 'XyzAbcA123',
                    'snake_name'            => 'xyz-abc-a123',
                    'vendor_name'           => env('MODULE_DEPLOYENV_REQUIRE_MODULES_DEFAULT_VENDOR'),
                    'module_snake_name'     => 'xyz-abc-a123',
                    'module_snake_name_git' => 'xyz-abc-a123-module',

                ],
            ],
            [
                'params' => 'yyy/system_base',
                'result' => [
                    'studly_name'           => 'SystemBase',
                    'snake_name'            => 'system-base',
                    'vendor_name'           => env('MODULE_DEPLOYENV_REQUIRE_MODULES_DEFAULT_VENDOR'),
                    'module_snake_name'     => 'system-base',
                    'module_snake_name_git' => 'system-base-module',

                ],
            ],
            [
                'params' => 'system_base',
                'result' => [
                    'studly_name'           => 'SystemBase',
                    'snake_name'            => 'system-base',
                    'vendor_name'           => 'AKlebeLaravel',
                    'module_snake_name'     => 'system-base',
                    'module_snake_name_git' => 'system-base-module',

                ],
            ],
            [
                'params' => 'WebsiteBase',
                'result' => [
                    'studly_name'           => 'WebsiteBase',
                    'snake_name'            => 'website-base',
                    'vendor_name'           => 'AKlebeLaravel',
                    'module_snake_name'     => 'website-base',
                    'module_snake_name_git' => 'website-base-module',

                ],
            ],
        ];

        /** @var ModuleService $moduleService */
        $moduleService = app(ModuleService::class);
        $this->runList($testList, function ($name, $data) use ($moduleService) {
            $result = $moduleService->getItemInfo(data_get($data, 'params'));
            foreach (data_get($data, 'result') as $k => $v) {
                if (data_get($result, $k) != $v) {
                    $this->fail(sprintf("Result for '%s': '%s', but expected was '%s'.", $k, $v,
                        data_get($result, $k)));
                }
            }
        });

        $this->assertTrue(true);
    }
}
