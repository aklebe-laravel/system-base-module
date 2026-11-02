<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Feature;

use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ModuleService::getItemInfo() with the installed modules (vendor names come from their composer.json).
 * Name conversions without installed modules: tests/Unit/Isolated/AddonServicesTest.
 */
class ModuleTest extends TestCase
{
    public static function itemInfoProvider(): array
    {
        return [
            'chaotic case'     => [
                'TestWith-chaotic_caseEx',
                ['studly_name' => 'TestWithChaoticCaseEx', 'snake_name' => 'test-with-chaotic-case-ex', 'module_snake_name' => 'test-with-chaotic-case-ex', 'module_snake_name_git' => 'test-with-chaotic-case-ex-module'],
            ],
            'digits'           => [
                'xyz_abc-a123',
                ['studly_name' => 'XyzAbcA123', 'snake_name' => 'xyz-abc-a123', 'module_snake_name' => 'xyz-abc-a123', 'module_snake_name_git' => 'xyz-abc-a123-module'],
            ],
            'git suffix'       => [
                'xyz_abc-a123-module',
                ['studly_name' => 'XyzAbcA123', 'snake_name' => 'xyz-abc-a123', 'module_snake_name' => 'xyz-abc-a123', 'module_snake_name_git' => 'xyz-abc-a123-module'],
            ],
            'foreign vendor'   => [
                'yyy/system_base',
                ['studly_name' => 'SystemBase', 'snake_name' => 'system-base', 'module_snake_name' => 'system-base', 'module_snake_name_git' => 'system-base-module'],
            ],
            'installed snake'  => [
                'system_base',
                ['studly_name' => 'SystemBase', 'snake_name' => 'system-base', 'vendor_name' => 'aklebe-laravel', 'module_snake_name' => 'system-base', 'module_snake_name_git' => 'system-base-module'],
            ],
            'installed studly' => [
                'WebsiteBase',
                ['studly_name' => 'WebsiteBase', 'snake_name' => 'website-base', 'vendor_name' => 'aklebe-laravel', 'module_snake_name' => 'website-base', 'module_snake_name_git' => 'website-base-module'],
            ],
        ];
    }

    #[DataProvider('itemInfoProvider')]
    public function test_getItemInfo(string $identifier, array $expected): void
    {
        $result = app(ModuleService::class)->getItemInfo($identifier);

        foreach ($expected as $key => $value) {
            $this->assertSame($value, data_get($result, $key), sprintf("Key '%s' of '%s'.", $key, $identifier));
        }
    }
}
