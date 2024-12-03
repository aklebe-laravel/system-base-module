<?php

namespace Modules\SystemBase\app\Providers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use Modules\SystemBase\app\Services\ModuleService;
use Nwidart\Modules\Facades\Module;
use ReflectionClass;
use Symfony\Component\Finder\SplFileInfo;

/**
 *
 */
class LivewireComponentServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerModuleComponents();

        $this->registerCustomModuleComponents();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * @return void
     */
    protected function registerModuleComponents(): void
    {
        /** @var ModuleService $moduleService */
        $moduleService = app(ModuleService::class);

        $modules = Module::toCollection();

        $modulesLivewireNamespace = config('modules-livewire.namespace', 'Livewire');

        $modules->each(function ($module) use ($modulesLivewireNamespace, $moduleService) {
            $directory = (string) Str::of($module->getPath())
                ->append('/'.$modulesLivewireNamespace)
                ->replace(['\\'], '/');

            $namespace = config('modules.namespace', 'Modules').'\\'.$module->getName().'\\'.$modulesLivewireNamespace;

            $this->registerComponentDirectory($directory, $namespace, $moduleService->getSnakeName($module).'::');
        });
    }

    /**
     * @return void
     */
    protected function registerCustomModuleComponents(): void
    {
        /** @var ModuleService $moduleService */
        $moduleService = app(ModuleService::class);

        $modules = collect(config('modules-livewire.custom_modules', []));

        $modules->each(function ($module, $moduleName) use ($moduleService) {
            $moduleLivewireNamespace = $module['namespace'] ?? config('modules-livewire.namespace', 'Livewire');

            $directory = (string) Str::of($module['path'] ?? '')
                ->append('/'.$moduleLivewireNamespace)
                ->replace(['\\'], '/');

            $namespace = ($module['module_namespace'] ?? $moduleName).'\\'.$moduleLivewireNamespace;

            $lowerName = $module['name_lower'] ?? $moduleService->getSnakeName($moduleName);

            $this->registerComponentDirectory($directory, $namespace, $lowerName.'::');
        });
    }

    /**
     * @param  string  $directory
     * @param  string  $namespace
     * @param  string  $aliasPrefix
     * @return false|void
     */
    protected function registerComponentDirectory(string $directory, string $namespace, string $aliasPrefix = '')
    {
        $filesystem = new Filesystem();

        if (!$filesystem->isDirectory($directory)) {
            return false;
        }

        collect($filesystem->allFiles($directory))->map(function (SplFileInfo $file) use ($namespace) {
                return (string) Str::of($namespace)->append('\\', $file->getRelativePathname())->replace(['/', '.php'],
                        ['\\', '']);
            })->filter(function ($class) {
                return is_subclass_of($class, Component::class) && !(new ReflectionClass($class))->isAbstract();
            })->each(function ($class) use ($namespace, $aliasPrefix) {
                $alias = $aliasPrefix.Str::of($class)
                        ->after($namespace.'\\')
                        ->replace(['/', '\\'], '.')
                        ->explode('.')
                        ->map([Str::class, 'kebab'])
                        ->implode('.');

                if (Str::endsWith($class, ['\Index', '\index'])) {
                    Livewire::component(Str::beforeLast($alias, '.index'), $class);
                }

                Livewire::component($alias, $class);
            });
    }
}
