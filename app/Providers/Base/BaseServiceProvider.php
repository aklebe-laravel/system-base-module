<?php

namespace Modules\SystemBase\app\Providers\Base;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider;

class BaseServiceProvider extends ServiceProvider
{
    /**
     * Inherit the given configuration with the existing configuration.
     *
     * @param  string  $path
     * @param  string  $key
     *
     * @return void
     * @throws BindingResolutionException
     */
    protected function mergeConfigFromRecursive(string $path, string $key): void
    {
        if (!($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $config = $this->app->make('config');

            if (!($content1 = @include $path)) {
                return;
            }
            if (!($content2 = $config->get($key, []))) {
                // $content2 = $config->get($key);
            }

            $merged = app('system_base')->arrayMergeRecursiveDistinct($content2, $content1, true);
            $config->set($key, $merged);
        }
    }

    /**
     * @param  string  $moduleLowerName
     *
     * @return string
     */
    protected function getCombinedModuleConfigKey(string $moduleLowerName): string
    {
        return 'combined-module-'.$moduleLowerName;
    }


}
