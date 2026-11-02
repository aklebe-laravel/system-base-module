<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SystemBase\app\Services\Base\AddonObjectService;
use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\app\Services\ThemeService;
use Modules\SystemBase\tests\TestCase;
use Nwidart\Modules\Module;

/**
 * SystemService parts depending on the booted application and the installed modules.
 * Everything else: tests/Unit/Isolated/SystemServiceTest.
 */
class SystemServiceTest extends TestCase
{
    /**
     * The user model of the enabled module with the highest priority wins
     * (SystemBaseServiceProvider binds it and syncs the auth config).
     */
    public function test_user_model_of_the_highest_priority_module_wins(): void
    {
        $expected = User::class;
        $expectedPriority = 1;

        /** @var ModuleService $moduleService */
        $moduleService = app(ModuleService::class);
        $moduleService->runOrderedEnabledModules(function (?Module $module) use (&$expected, &$expectedPriority, $moduleService) {
            $class = 'Modules\\'.$moduleService->getStudlyName($module).'\\app\\Models\\User';
            if (class_exists($class) && is_subclass_of($class, User::class) && (int) $module->getPriority() > $expectedPriority) {
                $expected = $class;
                $expectedPriority = (int) $module->getPriority();
            }

            return true;
        });

        $this->assertSame($expected, app('system_base')->getUserClassName());
        $this->assertSame($expected, config('auth.providers.users.database.model'));
        $this->assertInstanceOf($expected, app('user'));
        $this->assertInstanceOf($expected, app(Authenticatable::class));
    }

    public function test_hasInstanceClassOrTrait_with_app_bindings(): void
    {
        $this->assertTrue(app('system_base')->hasInstanceClassOrTrait(app('user'), HasFactory::class));
        $this->assertTrue(app('system_base')->hasInstanceClassOrTrait(app(ThemeService::class), AddonObjectService::class));
    }
}
