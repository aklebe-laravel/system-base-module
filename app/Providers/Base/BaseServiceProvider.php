<?php

namespace Modules\SystemBase\app\Providers\Base;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;

/**
 * @deprecated Remove this and use Traits, it's a code smell because a ServiceProvider should be hookable for dependency injections
 */
class BaseServiceProvider extends ServiceProvider
{
    /**
     * Merges the config file $path into config $key (recursively, the file wins).
     * Unlike mergeConfigFrom() nested arrays are merged, not replaced.
     * Does nothing if the config is cached or the file does not exist/returns nothing.
     *
     * @param  string  $path
     * @param  string  $key
     *
     * @return void
     * @throws BindingResolutionException
     */
    protected function mergeConfigFromRecursive(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        if (!is_file($path) || !is_array($content = include $path)) {
            return;
        }

        $config = $this->app->make('config');
        $merged = app('system_base')->arrayMergeRecursiveDistinct((array) $config->get($key, []), $content, true);
        $config->set($key, $merged);
    }

    /**
     * Config key like 'combined-module-my-module'.
     *
     * @param  string  $moduleLowerName
     *
     * @return string
     */
    protected function getCombinedModuleConfigKey(string $moduleLowerName): string
    {
        return 'combined-module-'.$moduleLowerName;
    }

}
