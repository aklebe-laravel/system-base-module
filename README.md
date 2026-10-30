# SystemBase Module

A **core** module of the [Mercy Scaffold](https://github.com/aklebe-laravel/mercy-scaffold).

This module provides the most basic operations for modules and themes: service helpers, base classes for
service providers and Livewire components, config merging, caching, parsing and tags.

Note: `ModuleBaseServiceProvider` uses `DeployEnv` (`DeploymentService`), so the module **DeployEnv** is required too.

### Config

See `config/config.php` for all values. Supported env vars:

| Env var                                         | Default | Meaning                                                       |
|-------------------------------------------------|---------|---------------------------------------------------------------|
| `SCHEDULE_ENABLED`                              | `true`  | `false` disables the schedules of all modules                 |
| `MODULE_SYSTEM_BASE_CACHE_ENABLED`              | `true`  | `false` disables `CacheService` completely                    |
| `MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL`          | `1`     | default ttl in seconds (used if a config ttl is missing)      |
| `MODULE_SYSTEM_BASE_CACHE_OBJECT_SIGNATURE_TTL` | `1`     | ttl for class lookups like `SystemService::findModuleClass()` |
| `MODULE_SYSTEM_BASE_CACHE_DB_SIGNATURE_TTL`     | `1`     | ttl for table names and column lists                          |
| `MODULE_SYSTEM_BASE_CACHE_TRANSLATIONS_TTL`     | `1`     | ttl for `SystemService::getTranslations()`                    |
| `MODULE_SYSTEM_BASE_CACHE_FRONTEND_TTL`         | `1`     | ttl for rendered frontend parts like message box buttons      |
| `MODULE_SYSTEM_BASE_CACHE_DEFAULT_TTL_LOW`      | `1`     | ttl for user based frontend content                           |

#### Config files merged over all modules

Every module can provide these files in its `config/` folder. They are merged by `ModuleBaseServiceProvider::registerConfig()`:

| File                                 | Accessed by                             | Meaning                                                      |
|--------------------------------------|-----------------------------------------|--------------------------------------------------------------|
| `config/config.php`                  | `config('my-module.xxx')`               | the module config                                            |
| `config/module-deploy-env.php`       | `config('my-module.module-deploy-env')` | deployments, see module DeployEnv                            |
| `config/message-boxes.php`           | `config('message-boxes')`               | message box buttons (php and javascript), merged recursively |
| `config/seeders.php`                 | `config('seeders')`                     | adjustments for seeding, merged recursively                  |
| `config/combined-module-{other}.php` | `config('combined-module-{other}')`     | config a module provides for another (enabled) module        |

### Services

All services are registered as singletons by `SystemBaseServiceProvider`. Short aliases:

| Alias / class               | Purpose                                                                                     |
|-----------------------------|---------------------------------------------------------------------------------------------|
| `app('system_base')`        | `SystemService`: array helpers, select options, env groups, class lookup, `parseEnv()`, ... |
| `app('system_base_module')` | `ModuleService`: module paths, namespaces, names, info, status (nwidart/laravel-modules)    |
| `app('system_base_file')`   | `FileService`: walk directories, copy, delete old files, sanitize paths                     |
| `app('system_base_path')`   | `PathService`: join paths                                                                   |
| `app('php_to_js')`          | `PhpToJsService`: data handed over to the frontend javascript                               |
| `app(CacheService::class)`  | cache wrapper, see below                                                                    |
| `app(ModelService::class)`  | query builder helpers like ids by "1,2,7-14"                                                |
| `app(ThemeService::class)`  | theme paths, parent themes, view files (alizharb/laravel-themer)                            |
| `app(GitService::class)`    | clone/update repositories, find the best tag/branch for a composer constraint               |
| `app(ParserService::class)` | placeholder parser, see below                                                               |
| `Csv`, `Encoding`           | CSV read/write and UTF-8/Windows-1252 conversion                                            |

All services extending `BaseService` share logging helpers (`debug()` only in debug mode, `info()`, `warning()`,
`error()`), an indent for nested processes and an error bag (`getErrors()`).

#### CacheService

Wraps the Laravel cache with these differences:

- ttl `0` (or negative) means **no caching** (the callback is always called)
- ttl `CacheService::ttlForever` caches forever
- `rememberUseConfig($key, 'system-base.cache.frontend.ttl', fn() => ...)` reads the ttl from config
- tags are only used if given, so stores without tag support (file, database) work too
- the cache is **not** separated by user, use `rememberFrontendUserBased()` for user content

#### ParserService

Replaces placeholders like `${{name attr='value' attr2="value2"}}`:

```php
$parser = app(ParserService::class);
$parser->setPlaceholders([
    'upper' => [
        'parameters' => [],
        'callback'   => fn(array $attributes, array $parameters, array $recursiveData) => strtoupper($attributes['v'] ?? ''),
    ],
]);
$parser->parse("Hello \${{upper v='world'}}"); // Hello WORLD
```

- attribute values can contain placeholders (resolved innermost first) and `\'`, `\"`, `\\` escapes
- built-in placeholders: `base_path path='...'`, `env name='...'`, `config name='...'`, `time`
- unknown placeholders throw an exception (`$allowThrowExceptions`), become `((PARSER_ERR:01))` (`$useInlineErrors`)
  or stay unchanged
- env vars like `${APP_URL}` are replaced by `SystemService::parseEnv()`, not by the parser

### Providers

#### ModuleBaseServiceProvider

Base provider all module providers extend. Set `$moduleName` (`MyModule`) and `$moduleNameLower` (`my-module`).
It registers translations, the merged configs (see above), views (`my-module::...`, published views in
`resources/views/modules/my-module` win) and migrations.
Model aliases can be bound by `$modelAliases` (like `'model-user' => User::class`).

#### ScheduleBaseServiceProvider

Base provider for module schedules. Overwrite `bootEnabledSchedule()` and optionally `bootDisabledSchedule()`.
Schedules are only booted in console. The whole scheduling can be disabled by config `system-base.schedule.enabled`
(env `SCHEDULE_ENABLED`), then a warning is logged every two hours.

#### LivewireComponentServiceProvider

Registers the Livewire components of all modules with aliases like `my-module::form.my-model`.

### Livewire

#### \Modules\SystemBase\app\Http\Livewire\BaseComponent

Base component for all Livewire components. Overwrite these hooks instead of the Livewire ones:

| Hook            | Runs                                                   |
|-----------------|--------------------------------------------------------|
| `initMount()`   | first request only                                     |
| `initHydrate()` | every following request (default: clears old messages) |
| `init()`        | after `initMount()` and after `initHydrate()`          |
| `initBooted()`  | every request                                          |

Messages: `addErrorMessage()`, `addSuccessMessage()`, `addInfoMessage()`, `addWarningMessage()`.
They are cleared after `$messagesLifeTime` seconds. Use `$tempSession = true` to keep them over a redirect.

### Tags

Models extending `ModelWithTags` (or using `TraitHasTags`) get polymorphic tags (tables `tags` and `taggables`):

- `$model->syncTags(['red', 'blue'])` creates missing tags and syncs them
- the virtual attribute `tag_names` reads the names of the loaded relation `tags2` and syncs on assignment
- `php artisan migration:legacy-tags "Modules\MyModule\app\Models\MyModel" tags` migrates an old
  comma separated (or JSON) text column to tags

### Console

| Command                                  | Meaning                                                      |
|------------------------------------------|--------------------------------------------------------------|
| `password:generate`                      | asks for a password and prints its hash                      |
| `password:generate --user=42`            | asks for a password and assigns it to the user (id or email) |
| `migration:legacy-tags {model} {column}` | see Tags                                                     |

### Tests

Unit tests in `tests/Unit/Isolated` run **without** booting the Laravel application (no database server, no HTTP).
They extend `IsolatedTestCase`, which builds a minimal container (config, log, cache, translator) per test.
DeployEnv uses this base class for its unit tests too.

```bash
vendor/bin/phpunit Modules/SystemBase/tests/Unit/Isolated
```

The other tests in `tests/Unit` and `tests/Feature` boot the application (`tests/TestCase.php`).

## Installation

Part of the Mercy core: the scaffold always installs it (`resources/setup/core.yaml`), it is not listed in `requires`.

## License

MIT, see [LICENSE.md](LICENSE.md).
