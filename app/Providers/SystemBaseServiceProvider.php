<?php

namespace Modules\SystemBase\app\Providers;

use App\Models\User as AppUser;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Console\MigrateLegacyTagsCommand;
use Modules\SystemBase\app\Console\Password;
use Modules\SystemBase\app\Providers\Base\ModuleBaseServiceProvider;
use Modules\SystemBase\app\Services\CacheService;
use Modules\SystemBase\app\Services\FileService;
use Modules\SystemBase\app\Services\ModelService;
use Modules\SystemBase\app\Services\ModuleService;
use Modules\SystemBase\app\Services\PathService;
use Modules\SystemBase\app\Services\PhpToJsService;
use Modules\SystemBase\app\Services\SystemService;
use ReflectionFunction;

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
     * Registers the console commands and provides broadcast channel names for javascript.
     *
     * @return void
     */
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([
                Password::class,
                MigrateLegacyTagsCommand::class,
            ]);
        }

        // @todo: find accurate place
        $channelPrefix = Str::studly(config('broadcasting.channel_prefix', ''));
        $channelName = $channelPrefix.'deployments_default-console';

        app('php_to_js')->addData('broadcast_channel_prefix', $channelPrefix);
        app('php_to_js')->addData('default_console_channel', $channelName);
    }

    /**
     * Registers the services as singletons (with short aliases like 'system_base') and the sub providers.
     *
     * @return void
     */
    public function register(): void
    {
        parent::register();

        // User model: modules bind App\Models\User to their own user class. Modules register in order
        // of their priority, so the module with the highest priority binds last and wins.
        // Only bind AppUser in modules, everything else follows it (see syncUserModel()).
        $this->app->bind('user', fn($app) => $app->make(AppUser::class));
        $this->app->bind(Authenticatable::class, fn($app) => $app->make(AppUser::class));
        $this->app->booted(fn() => $this->syncUserModel());

        $this->app->singleton(SystemService::class);
        $this->app->singleton('system_base', SystemService::class);
        $this->app->singleton(ModuleService::class);
        $this->app->singleton('system_base_module', ModuleService::class);
        $this->app->singleton(FileService::class);
        $this->app->singleton('system_base_file', FileService::class);
        $this->app->singleton(PathService::class);
        $this->app->singleton('system_base_path', PathService::class);
        $this->app->singleton(PhpToJsService::class);
        $this->app->singleton('php_to_js', PhpToJsService::class);
        $this->app->singleton(ModelService::class);
        // no alias for CacheService: 'cache' is reserved by Laravel
        $this->app->singleton(CacheService::class);

        $this->app->register(RouteServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);
        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Applies the final user class (binding of App\Models\User) to auth and the morph map.
     * Future modules only need to bind AppUser, nothing else.
     * Runs after all providers booted, so it also wins over config set in a boot() (like the LDAP config of Acl).
     *
     * @return void
     */
    protected function syncUserModel(): void
    {
        $userClass = $this->getBoundUserClass();

        // local eloquent model of the users provider (used by the LDAP provider, see Acl)
        Config::set('auth.providers.users.database.model', $userClass);

        // Eloquent provider (no LDAP): auth()->user() should be the winning user class as well.
        // Only if still the default, so an explicit AUTH_MODEL or another driver (ldap: 'model' is the
        // LDAP model) stays untouched. Not checked via env(): env() returns null once the config is cached.
        if (config('auth.providers.users.driver') === 'eloquent' && config('auth.providers.users.model') === AppUser::class) {
            Config::set('auth.providers.users.model', $userClass);
        }

        // polymorphic relations store 'users' instead of the class name
        Relation::morphMap(['users' => $userClass]);
    }

    /**
     * Class name bound to App\Models\User, without creating an instance: a user model may need
     * services (cache, database) that are not available everywhere, like in the build (package:discover).
     *
     * bind(AppUser::class, 'Some\\Class') keeps the class name inside the binding closure
     * (Container::getClosure()), so it is read from there. A binding with an own closure
     * can not be read without calling it, then AppUser::class is used.
     *
     * @return string
     */
    protected function getBoundUserClass(): string
    {
        $binding = $this->app->getBindings()[AppUser::class]['concrete'] ?? null;
        if ($binding instanceof Closure) {
            $concrete = (new ReflectionFunction($binding))->getStaticVariables()['concrete'] ?? null;
            if (is_string($concrete) && is_a($concrete, AppUser::class, true)) {
                return $concrete;
            }
        }

        return AppUser::class;
    }
}
