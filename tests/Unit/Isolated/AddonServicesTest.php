<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\app\Services\ThemeService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * ModuleService and ThemeService parts which don't need nwidart modules or themer.
 */
class AddonServicesTest extends IsolatedTestCase
{
    public static function moduleVendorInfoProvider(): array
    {
        return [
            'chaotic case'  => ['TestWith-chaotic_caseEx', true, '', '', 'test-with-chaotic-case-ex', 'test-with-chaotic-case-ex-module'],
            'with suffix'   => ['xyz_abc-a123-module', true, '', '', 'xyz-abc-a123', 'xyz-abc-a123-module'],
            'with vendor'   => ['acme/system_base', true, '', 'acme', 'system-base', 'system-base-module'],
            'vendor wins'   => ['acme/system-base', true, 'default-vendor', 'default-vendor', 'system-base', 'system-base-module'],
            'not validated' => ['acme/System_Base', false, '', 'acme', 'System_Base', 'System_Base-module'],
        ];
    }

    #[DataProvider('moduleVendorInfoProvider')]
    public function test_module_getVendorInfo(string $identifier, bool $validate, string $defaultVendor, string $vendor, string $snake, string $git): void
    {
        $service = new ModuleService();
        $service->vendor = $defaultVendor;

        $this->assertSame([
            'vendor_name'           => $vendor,
            'module_snake_name'     => $snake,
            'module_snake_name_git' => $git,
        ], $service->getVendorInfo($identifier, $validate));
    }

    public function test_theme_getVendorInfo(): void
    {
        $this->assertSame([
            'vendor_name'             => 'acme',
            'theme_snake_name'        => 'my-dark',
            'theme_snake_name_git'    => 'my-dark-theme',
            'theme_snake_name_folder' => 'my-dark',
        ], (new ThemeService())->getVendorInfo('acme/my_dark-theme', true));
    }

    public function test_names(): void
    {
        $this->assertSame('my-module', ModuleService::getSnakeName('MyModule'));
        $this->assertSame('my_module', ModuleService::getSnakeName('MyModule', '_'));
        $this->assertSame('MyModule', ModuleService::getStudlyName('my-module'));
        $this->assertSame('proxtop-server-vm', ModuleService::getModelSnakeName('ProxtopServerVm'));
    }

    public function test_module_getPath_and_getNamespace(): void
    {
        $this->setConfig('modules.namespace', 'Modules');
        $this->setConfig('modules.paths.generator.model.path', 'app/Models');

        $this->assertSame('/root/Modules/MyModule/app/Models/User.php', ModuleService::getPath('User.php', 'MyModule', 'model', '/root'));
        $this->assertSame('/root/Modules/MyModule', ModuleService::getPath('', 'MyModule', '', '/root'));
        $this->assertSame('Modules\\MyModule\\app\\Models\\User', ModuleService::getNamespace('MyModule', 'model', 'User'));
    }

    public function test_theme_getPath_with_root(): void
    {
        $this->assertSame('/root/themes/my-theme/theme.json', ThemeService::getPath('theme.json', 'my-theme', '', '/root'));
    }

    public function test_theme_getFilesInFolder_returns_view_names(): void
    {
        $root = $this->makeTempDir();
        $views = $root.'/themes/my-theme/views';
        $this->makeFile($views.'/layouts/master.blade.php');
        $this->makeFile($views.'/components/card/item.blade.php');

        $files = (new ThemeService())->getFilesInFolder($views, -1);
        $this->assertSame([], (new ThemeService())->getFilesInFolder($views, 0));
        ksort($files);

        $this->assertSame([
            'components.card.item' => 'components.card.item',
            'layouts.master'       => 'layouts.master',
        ], $files);
        $this->assertSame([], (new ThemeService())->getFilesInFolder($root.'/missing'));
    }

    public function test_module_setStatus_writes_statuses_file(): void
    {
        $file = $this->makeFile($this->makeTempDir().'/modules_statuses.json', '{"Acl": true}');
        $this->setConfig('modules.activators.file.statuses-file', $file);
        $service = new ModuleService();

        $this->assertTrue($service->setStatus('NewModule', true));
        $this->assertTrue($service->setStatus('Acl', false, true));
        $this->assertSame(['Acl' => true, 'NewModule' => true], json_decode(file_get_contents($file), true));

        $this->setConfig('modules.activators.file.statuses-file', $file.'.missing');
        $this->assertFalse($service->setStatus('X', true));
    }
}
