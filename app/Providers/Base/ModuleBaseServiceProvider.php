<?php

namespace Modules\SystemBase\app\Providers\Base;

use Illuminate\Support\Facades\Log;
use Modules\SystemBase\app\Services\ModuleService;
use Nwidart\Modules\Module;
use Throwable;

class ModuleBaseServiceProvider extends BaseServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected string $moduleName = 'xxx';

    /**
     * @var string $moduleNameLower
     */
    protected string $moduleNameLower = 'xxx';

    /**
     * Alias bindings for models.
     * Adjust it in your register() before you call parent::register()
     * recommend to use prefix "model-" (for example for user:  "model-user")
     *
     * @var array
     */
    protected array $modelAliases = [];

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(ModuleService::getPath('', $this->moduleName, 'migration'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        // bind model aliases (by default when parent::register() in your module)
        foreach ($this->modelAliases as $alias => $class) {
            $this->app->bind($alias, $class);
        }
    }

    /**
     * Merge config per module or all in one ...
     *
     * @param  string  $key
     * @param  bool    $perModule
     *
     * @return void
     */
    protected function mergeConfigEx(string $key, bool $perModule = false): void
    {
        try {
            $file = $key.'.php';
            if ($perModule) {
                // config for each module separately accessed by 'module.xxx.yyy'
                $key = $this->moduleNameLower.'.'.$key;
            }

            // otherwise, config for modules all in one accessed by 'xxx.yyy'
            $this->mergeConfigFromRecursive(ModuleService::getPath($file, $this->moduleName, 'config'), $key);

        } catch (Throwable $ex) {
            // file not found, ignore it ...
            Log::error($ex->getMessage());
        }
    }

    /**
     * Merge config per module or all in one ...
     *
     * @return void
     */
    protected function mergeCombinedConfigs(): void
    {
        // get all merged enabled module configs named 'combined-module-my-module-xyz.php'
        ModuleService::runOrderedEnabledModules(function (?Module $module) {
            try {
                if ($module) {
                    $moduleFoundSnakeName = ModuleService::getModelSnakeName($module);
                    $moduleFoundKey = $this->getCombinedModuleConfigKey($moduleFoundSnakeName);
                    $configFullPath = ModuleService::getPath($moduleFoundKey.'.php', $this->moduleName, 'config');

                    if (file_exists($configFullPath)) {
                        $this->mergeConfigFromRecursive($configFullPath, $moduleFoundKey);
                    }
                }
            } catch (Throwable $ex) {
                Log::error($ex->getMessage(), [__METHOD__]);
            }

            return true;
        });

    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig(): void
    {
        $c = ModuleService::getPath('config.php', $this->moduleName, 'config');
        $this->publishes([
            $c => config_path($this->moduleNameLower.'.php'),
        ], 'config');
        $this->mergeConfigFrom($c, $this->moduleNameLower);
        try {
            // config for each module separately accessed by 'module.xxx.yyy'
            $this->mergeConfigEx('module-deploy-env', true);

            // config for modules all in one accessed by 'xxx.yyy'
            $this->mergeConfigEx('message-boxes');
            $this->mergeConfigEx('seeders');

            //
            $this->mergeCombinedConfigs();

        } catch (Throwable) {
        }
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->moduleNameLower);

        $sourcePath = ModuleService::getPath('', $this->moduleName, 'views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $langPath = ModuleService::getPath('', $this->moduleName, 'lang');
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        }
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
     * @return array
     */
    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
