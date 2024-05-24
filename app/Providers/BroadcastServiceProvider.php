<?php

namespace Modules\SystemBase\app\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Facades\Module;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Broadcast::routes();

        require Module::getPath().'/SystemBase/routes/channels.php';
    }
}
