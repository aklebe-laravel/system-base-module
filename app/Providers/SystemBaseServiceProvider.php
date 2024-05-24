<?php

namespace Modules\SystemBase\app\Providers;

use App\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Providers\Base\ModuleBaseServiceProvider;
use Modules\SystemBase\app\Services\FileService;
use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\app\Services\PhpToJsService;
use Modules\SystemBase\app\Services\SystemService;

class SystemBaseServiceProvider extends ModuleBaseServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected string $moduleName = 'SystemBase';

    /**
     * @var string $moduleNameLower
     */
    protected string $moduleNameLower = 'system-base';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        // Make themes working
        Route::pushMiddlewareToGroup('web', \Shipu\Themevel\Middleware\WebMiddleware::class);

        // @todo: find accurate place
        $channelPrefix = Str::studly(env('BROADCAST_CHANNEL_PREFIX'));
        $channelName = $channelPrefix.'deployments_default-console';

        app('php_to_js')->addData('broadcast_channel_prefix', $channelPrefix);
        app('php_to_js')->addData('default_console_channel', $channelName);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->app->singleton('system_base', SystemService::class);
        $this->app->singleton('system_base_module', ModuleService::class);
        $this->app->singleton('system_base_file', FileService::class);
        $this->app->singleton('php_to_js', PhpToJsService::class);

        $this->app->register(RouteServiceProvider::class);
        $this->app->register(BroadcastServiceProvider::class);
        $this->app->register(LivewireComponentServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);
    }

}
