<?php

namespace Modules\SystemBase\app\Providers\Base;

use Illuminate\Support\Facades\Log;
use Modules\DeployEnv\app\Services\DeploymentService;
use Modules\SystemBase\app\Services\ModuleService;
use Nwidart\Modules\Module;
use Throwable;

/**
 * Base provider for module service providers.
 * Registers translations, config (inclusive merged configs, see registerConfig()), views and migrations.
 *
 * Set $moduleName (studly, like 'MyModule') and $moduleNameLower (snake, like 'my-module') in your provider.
 *
 * @deprecated Remove this and use Traits, it's a code smell because a ServiceProvider should be hookable for dependency injections
 */
class ModuleBaseServiceProvider extends BaseServiceProvider
{
    /**
     * Module name in studly case like 'MyModule'.
     *
     * @var string $moduleName
     */
    protected string $moduleName = 'xxx';

    /**
     * Module name in snake case like 'my-module'. Used as config key, view and translation namespace.
     *
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
     * Registers translations, config, views and migrations of the module.
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
     * Registers the DeploymentService singleton and the model aliases.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(DeploymentService::class);

        // bind model aliases (by default when parent::register() in your module)
        foreach ($this->modelAliases as $alias => $class) {
            $this->app->bind($alias, $class);
        }
    }

    /**
     * Merges the module file config/{$key}.php into the config:
     * - $perModule = false: all modules into one key, accessed by config('{key}.xxx')
     * - $perModule = true: per module, accessed by config('{module-snake}.{key}.xxx')
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

            $this->mergeConfigFromRecursive(ModuleService::getPath($file, $this->moduleName, 'config'), $key);

        } catch (Throwable $ex) {
            Log::error($ex->getMessage(), [__METHOD__]);
        }
    }

    /**
     * A module can provide config for other modules:
     * a file config/combined-module-{other-module-snake}.php in THIS module
     * is merged into config('combined-module-{other-module-snake}').
     * Only files for enabled modules are merged.
     *
     * @return void
     */
    protected function mergeCombinedConfigs(): void
    {
        ModuleService::runOrderedEnabledModules(function (?Module $module) {
            try {
                if ($module) {
                    $moduleFoundSnakeName = ModuleService::getSnakeName($module);
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
     * Registers the module config:
     * - config/config.php as config('{module-snake}')
     * - config/module-deploy-env.php as config('{module-snake}.module-deploy-env') (see DeployEnv)
     * - config/message-boxes.php and config/seeders.php merged over all modules
     * - config/combined-module-*.php, see mergeCombinedConfigs()
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
            $this->mergeConfigEx(DeploymentService::MODULE_CONFIG_IDENT, true);
            $this->mergeConfigEx('message-boxes');
            $this->mergeConfigEx('seeders');
            $this->mergeCombinedConfigs();
        } catch (Throwable $ex) {
            Log::error($ex->getMessage(), [__METHOD__]);
        }
    }

    /**
     * Registers the views with the namespace '{module-snake}::'.
     * Published/overwritten views in resources/views/modules/{module-snake} win.
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
     * Registers translations: resources/lang/modules/{module-snake} if it exists, otherwise the module lang folder.
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
     * Existing folders {view path}/modules/{module-snake}.
     *
     * @return array
     */
    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths', []) as $path) {
            if (is_dir($path.'/modules/'.$this->moduleNameLower)) {
                $paths[] = $path.'/modules/'.$this->moduleNameLower;
            }
        }

        return $paths;
    }
}
