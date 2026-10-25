<?php
declare(strict_types = 1);

namespace Modules\SystemBase\app\Services;

use Closure;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\BaseService;

class SystemService extends BaseService
{
    const string dateIsoFormat8601 = 'Y-m-d H:i:s';

    const int SortModeNone = 0x0000;
    const int SortModeByKey = 0x0001;
    const int SortModeByValue = 0x0002;
    const int SortModeAsc = 0x0100;
    const int SortModeDesc = 0x0200;

    const string EnvGroupTesting = 'testing';
    const string EnvGroupDevelopment = 'development';
    const string EnvGroupImportant = 'important';
    const string EnvGroupProduction = 'production';

    /**
     * Environment names grouped by purpose. See isInEnvGroup().
     */
    const array EnvGroups = [
        // any kind of testing environments
        self::EnvGroupTesting     => [
            'testing',
            'dusk',
        ],
        // unstable envs like local and dev
        self::EnvGroupDevelopment => [
            'local',
            'dev',
            'development',
        ],
        // stable and safe (production like) environments, including staging
        self::EnvGroupImportant   => [
            'demo',
            'int',
            'staging',
            'prod',
            'production',
        ],
        // only real production with public/unknown users, no staging, but demo
        self::EnvGroupProduction  => [
            'prod',
            'production',
            'demo',
        ],
    ];

    /**
     * Marker for "no value found" in hasData(), because null can be a real value.
     */
    const string UNDEFINED_CONTENT = '##__UNDEFINED_CONTENT__##';

    /**
     * Counters used by addUniqueCounter() / getUniqueCounter().
     *
     * @var array<string, int>
     */
    protected array $uniqueCounters = [];

    /**
     * Special select option value: "all".
     */
    const string selectValueAll = '_all_';

    /**
     * Special select option value: "no choice" (nothing selected yet).
     */
    const string selectValueNoChoice = '_no_choice_';

    /**
     * Special select option value: "none".
     */
    const string selectValueNone = '_none_';

    /**
     * Special select options as key => untranslated label.
     * Translated variants are built in __construct().
     *
     * @var array<string, string>
     */
    public array $allSelectOptionsRaw = [
        self::selectValueAll      => 'All',
        self::selectValueNoChoice => 'No choice',
        self::selectValueNone     => 'None',
    ];

    /**
     * Special select options in the format of toSelectOptionSimple(), built in __construct().
     *
     * @var array<string, array<string, string>>
     */
    public array $selectOptionsSimple = [];

    /**
     * Special select options in the format of toSelectOptionCompact(), built in __construct().
     *
     * @var array<string, array<string, array>>
     */
    public array $selectOptionsCompact = [];

    /**
     * Builds the translated special select options.
     */
    public function __construct()
    {
        parent::__construct();

        foreach ($this->allSelectOptionsRaw as $k => $v) {
            $this->selectOptionsSimple[$k] = $this->toSelectOptionSimple($v, $k);
            $this->selectOptionsCompact[$k] = $this->toSelectOptionCompact($v, $k);
        }
    }

    /**
     * Try always to force an array result.
     * - null and scalars are wrapped like [$value]
     * - objects are converted deeply by json_encode()/json_decode()
     * - arrays are returned unchanged
     *
     * @param  mixed  $nestedObject
     *
     * @return mixed
     */
    public function toArray(mixed $nestedObject): mixed
    {
        if (($nestedObject === null) || is_scalar($nestedObject)) {
            return [$nestedObject];
        }

        if (is_object($nestedObject)) {
            return json_decode(json_encode($nestedObject), true);
        }

        return $nestedObject;
    }

    /**
     * Unlike toArray() this method will not force scalars into an array and has the following benefits:
     * 1) Faster than json_decode(json_encode())
     * 2) No camel to snake conversion
     *
     * Only public properties of objects are converted (cast to array).
     *
     * @param  mixed  $data
     * @param  int    $maxDeep  levels to convert, deeper objects stay objects
     *
     * @return array|mixed
     */
    public function toArrayRaw(mixed $data, int $maxDeep = 2): mixed
    {
        if ($maxDeep >= 1) {
            if (is_array($data) || is_object($data)) {
                return array_map(function ($value) use ($maxDeep) {
                    return (is_array($value) || is_object($value)) ? $this->toArrayRaw($value,
                        $maxDeep - 1) : $value;
                }, (array) $data);
            }
        }

        return $data;
    }

    /**
     * Merges $source into $destination recursively.
     * Unlike array_merge_recursive() existing keys are replaced, not turned into lists.
     *
     * - $forceOverride = true: every source value wins, also null (unconditional inheritance)
     * - $ignoreNull = true: a null value in $source does not overwrite $destination,
     *   except the key is listed in $butForceOverrideKeys
     *
     * All options are passed to nested levels.
     *
     * @param  array  $destination
     * @param  array  $source
     * @param  bool   $forceOverride  it's like unconditional inheritance
     * @param  bool   $ignoreNull
     * @param  array  $butForceOverrideKeys
     *
     * @return array
     */
    public function arrayMergeRecursiveDistinct(array $destination, array $source, bool $forceOverride = false, bool $ignoreNull = true, array $butForceOverrideKeys = []): array
    {
        foreach ($source as $key => $value) {
            if (is_array($value) && isset($destination[$key]) && is_array($destination[$key])) {
                $destination[$key] = $this->arrayMergeRecursiveDistinct($destination[$key], $value, $forceOverride, $ignoreNull, $butForceOverrideKeys);
            } elseif ($forceOverride || !$ignoreNull || ($value !== null) || in_array($key, $butForceOverrideKeys, true)) {
                $destination[$key] = $value;
            }
        }

        return $destination;
    }

    /**
     * True if every key of $a exists in $b with the same value.
     * Nested arrays are compared with arrayCompareIsEqual(), everything else strictly (===).
     * Despite the name: it checks "is A contained in B".
     *
     * @param  array  $a
     * @param  array  $b
     *
     * @return bool
     */
    public function arrayCompareIsBInA(array &$a, array &$b): bool
    {
        foreach ($a as $k => $aItem) {
            // array_key_exists() instead of hasData(): keys containing dots must not be treated as paths
            if (!array_key_exists($k, $b)) {
                return false;
            }
            if (is_array($aItem)) {
                if (!is_array($b[$k]) || !$this->arrayCompareIsEqual($aItem, $b[$k])) {
                    return false;
                }
            } elseif ($aItem !== $b[$k]) {
                return false;
            }
        }

        return true;
    }

    /**
     * True if both arrays have the same keys and values (order of keys is ignored).
     *
     * @param  array  $a
     * @param  array  $b
     *
     * @return bool
     */
    public function arrayCompareIsEqual(array &$a, array &$b): bool
    {
        if (!$this->arrayCompareIsBInA($a, $b)) {
            return false;
        }
        if (!$this->arrayCompareIsBInA($b, $a)) {
            return false;
        }

        return true;
    }

    /**
     * Copies root keys listed as keys in $whitelistArray from $arraySrc to $arrayDest, but no arrays.
     * Useful to inherit parent key:values like disabled:false in a nested array.
     * Keys with null values in $arraySrc are not copied.
     *
     * @param  array  $arrayDest
     * @param  array  $arraySrc
     * @param  array  $whitelistArray
     *
     * @return array
     */
    public function arrayRootCopyWhitelistedNoArrays(array $arrayDest, array $arraySrc, array $whitelistArray): array
    {
        foreach ($whitelistArray as $key => $valueTemp) {
            if (isset($arraySrc[$key])) {
                $value = $arraySrc[$key];

                if (!is_array($value)) {
                    $arrayDest[$key] = $value;
                }
            }
        }

        return $arrayDest;
    }


    /**
     * Builds select options as [key => label] from a list of items (arrays, objects or models).
     *
     * $valueProperty (the label):
     * - null: the item itself
     * - '[key]': the list key
     * - string: dot path into the item (data_get())
     * - array of paths: values joined by ' | ' (missing values as '-')
     * - Closure: called with the item
     *
     * $keyProperty (the option key):
     * - null: the list key (or the value, if $valueProperty is null too)
     * - '[key]': the list key
     * - string: dot path into the item
     *
     * $sortMode: combine SortModeByKey or SortModeByValue with SortModeAsc or SortModeDesc.
     * Keys are always preserved. Without SortModeAsc the sort is descending.
     *
     * @param  iterable|null  $list
     * @param  mixed|null     $valueProperty
     * @param  string|null    $keyProperty
     * @param  array          $first  options placed before all others (like "no choice")
     * @param  int            $sortMode
     *
     * @return array
     */
    public function toHtmlSelectOptions(?iterable $list, mixed $valueProperty = null, ?string $keyProperty = null, array $first = [], int $sortMode = self::SortModeNone): array
    {
        $options = [];
        if ($list) {

            foreach ($list as $k => $v) {

                if ($keyProperty === '[key]') {
                    $key = $k;
                } else {
                    $key = ($keyProperty === null) ? $k : data_get($v, $keyProperty);
                }

                // Closure?
                if ($this->isCallableClosure($valueProperty)) {
                    $value = $valueProperty($v);
                } elseif (is_array($valueProperty)) {// Multiple values as array?
                    $value = '';
                    foreach ($valueProperty as $vp) {
                        if ($value) {
                            $value .= ' | ';
                        }
                        $value .= data_get($v, $vp, '-');
                    }
                } else {
                    if ($valueProperty === '[key]') {
                        $value = $k;
                    } else {
                        $value = ($valueProperty === null) ? $v : data_get($v, $valueProperty);

                        // If value property AND key property is null, both value and key are the same (value)
                        if (($valueProperty === null) && ($keyProperty === null)) {
                            $key = $value;
                        }
                    }
                }

                $options[$key] = $value;
            }

            // SORT_FLAG_CASE only works together with SORT_STRING (or SORT_NATURAL).
            // The *sort() variants used here keep the keys, otherwise the option keys would be lost.
            $sortFlags = SORT_NATURAL | SORT_FLAG_CASE;
            if ($sortMode & self::SortModeByKey) {
                if ($sortMode & self::SortModeAsc) {
                    ksort($options, $sortFlags);
                } else {
                    krsort($options, $sortFlags);
                }
            }
            if ($sortMode & self::SortModeByValue) {
                if ($sortMode & self::SortModeAsc) {
                    asort($options, $sortFlags);
                } else {
                    arsort($options, $sortFlags);
                }
            }

        }

        if ($first) {
            $options = $first + $options;
        }

        return $options;
    }

    /**
     * Single select option like ['_none_' => '[None]'] with translated label.
     *
     * @param  string  $label
     * @param  string  $key
     *
     * @return string[]
     */
    public function toSelectOptionSimple(string $label, string $key = ''): array
    {
        return [$key => '['.__($label).']'];
    }

    /**
     * Single select option like ['_none_' => ['label' => '[None]', ...$extraData]] with translated label.
     *
     * @param  string  $label
     * @param  string  $key
     * @param  array   $extraData
     *
     * @return array[]
     */
    public function toSelectOptionCompact(string $label, string $key = '', array $extraData = []): array
    {
        return [
            $key => [
                'label' => '['.__($label).']',
                ... $extraData,
            ],
        ];
    }

    /**
     * Compares two strings case-insensitive, also for umlauts (multibyte safe).
     * Examples:
     * - strCaseCompare('de', 'DE') = true
     * - strCaseCompare('über', 'ÜBER') = true
     *
     * @param  string  $stringA
     * @param  string  $stringB
     *
     * @return bool
     */
    public function strCaseCompare($stringA, $stringB): bool
    {
        return (strcoll(Str::lower($stringA), Str::lower($stringB)) === 0);
    }

    /**
     * Use this instead of is_callable() to prevent strings like 'max' from being treated as functions.
     * Accepted: Closure instances and callable arrays like [MyClass::class, 'method'].
     *
     * @param  mixed  $f
     *
     * @return bool
     */
    public function isCallableClosure($f): bool
    {
        return ($f instanceof Closure) // callback function()
               || (is_array($f) && is_callable($f)); // or [xxx::class, 'method']
    }

    /**
     * Returns $f as callable:
     * - Closure: returned as is
     * - [class name, non-static method]: the class is resolved by the container
     * - other callable arrays like [object, method] or [class name, static method]: returned as is
     * - everything else (also function names like 'max'): null
     *
     * @param  mixed  $f
     *
     * @return callable|null
     */
    public function getCallable($f): ?callable
    {
        if ($f instanceof Closure) {
            return $f;
        }

        if (!is_array($f) || (count($f) !== 2)) {
            return null;
        }

        [$class, $method] = array_values($f);
        if (is_string($class) && is_string($method) && method_exists($class, $method)
            && !(new \ReflectionMethod($class, $method))->isStatic()
        ) {
            return [app($class), $method];
        }

        return is_callable($f) ? $f : null;
    }

    /**
     * Resolves a model instance by its short class name, see findModuleClass().
     *
     * @param  string  $classNameWithoutNamespace
     *
     * @return Model
     * @throws BindingResolutionException
     */
    public function getEloquentModel(string $classNameWithoutNamespace): Model
    {
        $moduleClass = $this->findModuleClass($classNameWithoutNamespace);

        return App::make($moduleClass);
    }

    /**
     * Query builder of a model resolved by its short class name, see findModuleClass().
     *
     * @param  string  $classNameWithoutNamespace
     *
     * @return Builder
     * @throws BindingResolutionException
     */
    public function getEloquentModelBuilder(string $classNameWithoutNamespace): Builder
    {
        return $this->getEloquentModel($classNameWithoutNamespace)
            ->query();
    }

    /**
     * Extracts the classname from a full namespace.
     *
     * @param  string  $fullNamespace
     *
     * @return string classname or empty string
     */
    public function getSimpleClassName(string $fullNamespace): string
    {
        if (($i = strrpos($fullNamespace, '\\')) !== false) {
            return substr($fullNamespace, $i + 1);
        }

        return '';
    }

    /**
     * Formats a price like "12.50 EUR".
     * A price of 0 is shown as text depending on the payment method (offer, exchange, give away).
     *
     * @param  mixed        $price
     * @param  string|null  $currency
     * @param  string|null  $paymentMethodCode
     *
     * @return string
     */
    public function getPriceFormatted(mixed $price, ?string $currency = '', ?string $paymentMethodCode = ''): string
    {
        if ($price = (float) $price) {
            $formatted = number_format($price, 2).' '.($currency ?? '');
        } else {
            switch ($paymentMethodCode) {
                case 'offer':
                case 'negotiable': // @deprecated?
                case 'exchange_of_goods':
                    $formatted = __('payment_method_'.$paymentMethodCode);
                    break;
                default:
                    $formatted = __('give away');
                    break;
            }
        }

        return $formatted;
    }

    /**
     * Localized date like "Monday, 19.10.2026".
     *
     * @param  mixed  $time  anything Carbon::parse() accepts
     *
     * @return string
     */
    public function formatDate($time): string
    {
        $timeLocale = Carbon::parse($time);

        return $timeLocale->dayName.', '.$timeLocale->translatedFormat('d.m.Y');
    }

    /**
     * Localized time like "13:45h ".
     *
     * @param  mixed  $time  anything Carbon::parse() accepts
     *
     * @return string
     */
    public function formatTime($time): string
    {
        $timeLocale = Carbon::parse($time);

        return $timeLocale->translatedFormat('H:i').'h ';
    }

    /**
     * Short human-readable difference to now like "3h".
     *
     * @param  mixed  $time  anything Carbon::parse() accepts
     *
     * @return string
     */
    public function formatTimeDiff($time): string
    {
        $timeLocale = Carbon::parse($time);

        return $timeLocale->shortAbsoluteDiffForHumans();
    }

    /**
     * Seconds since $startTime, default: since the request/command started (LARAVEL_START).
     *
     * @param  float|null  $startTime
     *
     * @return float
     */
    public function getExecutionTime(?float $startTime = null): float
    {
        $startTime ??= $this->getLaravelStartTime();

        return max(0.0, microtime(true) - $startTime);
    }

    /**
     * Writes the execution time since LARAVEL_START to the debug log.
     *
     * @param  string  $name
     *
     * @return void
     */
    public function logExecutionTime(string $name): void
    {
        $t = Carbon::createFromTimestamp($this->getLaravelStartTime());
        $this->debug('Script "'.$name.'" execution time: '.$t->longAbsoluteDiffForHumans());
    }

    /**
     * LARAVEL_START is defined in public/index.php and artisan, but not in every context (like unit tests).
     *
     * @return float
     */
    protected function getLaravelStartTime(): float
    {
        return defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true);
    }

    /**
     * Determine whether $subject matches one of the patterns in $patternList.
     *
     * @param  string  $subject
     * @param  array   $patternList
     * @param  string  $addDelimiters  if set (like '#'), every pattern is wrapped by it
     *
     * @return bool
     */
    public function isInRegexList(string $subject, array $patternList = [], string $addDelimiters = ''): bool
    {
        foreach ($patternList as $pattern) {

            if ($addDelimiters) {
                $pattern = $addDelimiters.$pattern.$addDelimiters;
            }

            if (preg_match($pattern, $subject)) {
                return true;
            }

        }

        return false;
    }

    /**
     * Finds a class in the enabled modules (highest priority first) and at last in the app.
     * So modules have the power to overwrite app classes.
     * The result is cached (config 'system-base.cache.object.signature.ttl').
     *
     * @param  string  $className       short class name like 'User'
     * @param  string  $generatorKey    nwidart config key for the path (like 'model', 'config' or 'views')
     * @param  bool    $returnInfoData  true: returns ['class', 'module', 'module_snake_name'] instead of the class name
     * @param  string  $forceModule     search in this module only
     *
     * @return string|array  '' or [] if nothing was found
     */
    public function findModuleClass(string $className, string $generatorKey = 'model', bool $returnInfoData = false, string $forceModule = ''): string|array
    {
        return app(CacheService::class)->rememberUseConfig("findModule_{$forceModule}_{$className}_{$generatorKey}_".($returnInfoData ? '1' : '0'),
            'system-base.cache.object.signature.ttl',
            function () use ($className, $generatorKey, $returnInfoData, $forceModule) {

                /** @var ModuleService $moduleService */
                $moduleService = app(ModuleService::class);
                $modules = ModuleService::getOrderedEnabledModules(true);
                foreach ($modules as $module) {

                    if ($forceModule && ($module->getName() !== $forceModule)) {
                        continue;
                    }

                    $currentClassNameGenerated = ModuleService::getNamespace($moduleService->getStudlyName($module), $generatorKey, $className);

                    try {
                        if (class_exists($currentClassNameGenerated)) {

                            if ($returnInfoData) {
                                return [
                                    'class'             => $currentClassNameGenerated,
                                    'module'            => $module->getName(),
                                    'module_snake_name' => $moduleService->getSnakeName($module),
                                ];
                            }

                            return $currentClassNameGenerated;
                        }
                    } catch (Exception) {
                    }
                }

                // at last check the app has defined this class
                // @todo: no config for $generatorKey, path should be uppercase maybe?
                $currentClassNameGenerated = sprintf('App\%s\%s', ucfirst($generatorKey), $className);
                try {
                    if (class_exists($currentClassNameGenerated)) {
                        if ($returnInfoData) {
                            return [
                                'class'             => $currentClassNameGenerated,
                                'module'            => '',
                                'module_snake_name' => '',
                            ];
                        }

                        return $currentClassNameGenerated;
                    }
                } catch (Exception) {
                }

                // if info data wanted, return at least an empty object!
                if ($returnInfoData) {
                    return [];
                }

                return "";
            });

    }

    /**
     * Cached table name of a model class.
     *
     * @param  string  $modelClass
     *
     * @return string
     */
    public function getModelTable(string $modelClass): string
    {
        return app(CacheService::class)->rememberUseConfig('table_name_of_model_'.$modelClass, 'system-base.cache.db.signature.ttl', function () use ($modelClass) {
            return app($modelClass)->getTable();
        });
    }

    /**
     * Livewire component name of a class like "module-snake::form.my-model".
     *
     * @param  string  $className
     * @param  string  $generatorKey  nwidart config key for the path (like 'model', 'config' or 'views')
     * @param  string  $forceModule
     *
     * @return string
     * @throws Exception  if no class was found
     */
    public function findLivewire(string $className, string $generatorKey = '', string $forceModule = ''): string
    {
        // Get class infos like module ...
        if (!($classInfo = $this->findModuleClass($className, $generatorKey, true, $forceModule))) {
            throw new Exception(sprintf("Missing livewire component: '%s' in '%s'", $className, $generatorKey));
            //$className = BaseDataTable::class;
        }

        $livewireName = data_get($classInfo, 'module_snake_name');

        // If module found then add it like "module-x::"
        if ($livewireName) {
            $livewireName .= '::';
        }

        if ($generatorKey) {
            $livewireClassParts = explode('\\', data_get($classInfo, 'class'));
            $livewireType = $livewireClassParts[count($livewireClassParts) - 2]; // Form or DataTable
            $livewireName .= app('system_base_module')->getModelSnakeName($livewireType).'.';
        }

        $livewireName .= app('system_base_module')->getModelSnakeName($className);

        return $livewireName;
    }

    /**
     * Get all translation as list indexed by language.
     *
     * @param  array  $langList
     *
     * @return array
     */
    public function getTranslations(array $langList = ['de']): array
    {
        return app(CacheService::class)->rememberUseConfig('system_base_translations_'.json_encode($langList), 'system-base.cache.translations.ttl', function () use ($langList) {
            $result = [];
            foreach ($langList as $lang) {
                $result[$lang] = trans('*', [], $lang);
            }

            return $result;
        });
    }

    /**
     * Toggle global debug mode.
     *
     * @param  bool  $debugEnabled
     *
     * @return void
     */
    public function switchEnvDebug(bool $debugEnabled): void
    {
        config(['app.debug' => $debugEnabled]);
    }

    /**
     * Checks whether an instance has a class or a trait.
     * Use this instead of 'instanceof' when testing traits.
     *
     * @param  mixed   $instance      instance or class name we want to check
     * @param  string  $classOrTrait  class or trait we are looking for (inside $instance)
     *
     * @return bool
     */
    public function hasInstanceClassOrTrait(mixed $instance, string $classOrTrait): bool
    {
        if (is_scalar($instance)) {
            return false;
        }

        // trait_uses_recursive() is part of class_uses_recursive()
        return (in_array($classOrTrait, class_uses_recursive($instance)) || ($instance instanceof $classOrTrait));
    }

    /**
     * Checks whether a dot path exists in an object/array. Also true if the value is null.
     *
     * @param  mixed                  $object
     * @param  string|int|array|null  $keyPath
     *
     * @return bool
     */
    public function hasData(mixed $object, $keyPath): bool
    {
        return (data_get($object, $keyPath, self::UNDEFINED_CONTENT) !== self::UNDEFINED_CONTENT);
    }

    /**
     * Cached column names of a database table.
     *
     * @param  string  $tableName
     *
     * @return array
     */
    public function getDbColumns(string $tableName): array
    {
        return app(CacheService::class)->rememberUseConfig('db_columns_from_table_'.$tableName, 'system-base.cache.db.signature.ttl', function () use ($tableName) {
            return Schema::getColumnListing($tableName);
        });
    }

    /**
     * Used to detect the current inherited user model.
     *
     * @return string
     */
    public function getUserClassName(): string
    {
        // Class name bound to User::class.
        // Do not use app()->getAlias(User::class) anymore.
        // This way is the cleanest for relations like belongsTo()
        return config('auth.providers.users.database.model');
    }

    /**
     * Increment the unique counter and return it.
     * Can be used for frontend HTML ids.
     *
     * @param  string  $key
     *
     * @return int
     */
    public function addUniqueCounter(string $key): int
    {
        $c = data_get($this->uniqueCounters, $key, 0);
        data_set($this->uniqueCounters, $key, $c + 1);

        return $this->getUniqueCounter($key);
    }

    /**
     * Return the unique counter.
     *
     * @param  string  $key
     *
     * @return int
     */
    public function getUniqueCounter(string $key): int
    {
        return data_get($this->uniqueCounters, $key, 0);
    }

    /**
     * Returns all items of $sourceList not matching any of the patterns (keys are re-indexed).
     *
     * @param  array  $sourceList
     * @param  array  $regexPatternBlacklist
     *
     * @return array
     */
    public function removeBlacklistItems(array $sourceList, array $regexPatternBlacklist = []): array
    {
        $result = [];
        foreach ($sourceList as $sourceValue) {
            if (!$this->isInRegexList($sourceValue, $regexPatternBlacklist)) {
                $result[] = $sourceValue;
            }
        }

        return $result;
    }

    /**
     * Formats bytes with binary units like "1.5 KiB".
     *
     * @param  int|float  $bytes
     *
     * @return string
     */
    public function bytesToHuman($bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        $lastUnit = count($units) - 1;

        // ">= 1024" so exactly 1024 bytes becomes "1 KiB", and never run past the last unit
        for ($i = 0; ($bytes >= 1024) && ($i < $lastUnit); $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * True if the environment (default: app.env) belongs to the group, see EnvGroups.
     *
     * @param  string       $group  one of the EnvGroup* constants
     * @param  string|null  $env
     *
     * @return bool
     */
    public function isInEnvGroup(string $group, ?string $env = null): bool
    {
        if ($env === null) {
            $env = config('app.env');
        }

        return in_array($env, self::EnvGroups[$group] ?? [], true);
    }

    /**
     * @param  string|null  $env
     *
     * @return bool
     */
    public function isEnvGroupImportant(?string $env = null): bool
    {
        return $this->isInEnvGroup(self::EnvGroupImportant, $env);
    }

    /**
     * @param  string|null  $env
     *
     * @return bool
     */
    public function isEnvGroupProduction(?string $env = null): bool
    {
        return $this->isInEnvGroup(self::EnvGroupProduction, $env);
    }

    /**
     * @param  string|null  $env
     *
     * @return bool
     */
    public function isEnvGroupDevelopment(?string $env = null): bool
    {
        return $this->isInEnvGroup(self::EnvGroupDevelopment, $env);
    }

    /**
     * @param  string|null  $env
     *
     * @return bool
     */
    public function isEnvGroupTesting(?string $env = null): bool
    {
        return $this->isInEnvGroup(self::EnvGroupTesting, $env);
    }

    /**
     * Walks through a nested array.
     * - $callbackScalar($key, $value, $currentRoot, $deep) for every null/scalar value
     * - $callbackEveryNode($key, $value, $currentRoot, $deep) for every non-scalar value before it is walked into
     * $currentRoot is the dot path of the parent node, $deep starts with 1.
     *
     * @param  array          $array
     * @param  callable       $callbackScalar
     * @param  string         $currentRoot
     * @param  int            $currentDeep
     * @param  callable|null  $callbackEveryNode
     *
     * @return void
     */
    public function runThroughArray(array $array, callable $callbackScalar, string $currentRoot = '', int $currentDeep = 0, ?callable $callbackEveryNode = null): void
    {
        foreach ($array as $key => $value) {
            if (($value === null) || is_scalar($value)) {
                $callbackScalar($key, $value, $currentRoot, $currentDeep + 1);
            } else {
                if ($callbackEveryNode !== null) {
                    $callbackEveryNode($key, $value, $currentRoot, $currentDeep + 1);
                }
                $this->runThroughArray($value, $callbackScalar, ($currentRoot ? ($currentRoot.'.') : '').$key, $currentDeep + 1, $callbackEveryNode);
            }
        }
    }

    /**
     * Builds [value => value] from a list, like ['a', 'b'] => ['a' => 'a', 'b' => 'b'].
     *
     * @param  array|null  $a
     *
     * @return array
     */
    public function assignArrayKeysByValue(?array $a): array
    {
        $result = [];
        if ($a === null) {
            return $result;
        }

        foreach ($a as $value) {
            $result[$value] = $value;
        }

        return $result;
    }

    /**
     * Updates an array value only if it differs from the current state.
     * Handles deep comparison for arrays and objects automatically via '==='.
     *
     * @param  array   $target  The reference to your public property (e.g., &$this->dataTransfer)
     * @param  string  $path    Dot-notation path
     * @param  mixed   $value   The new value to set
     *
     * @return bool True if data was changed, false if identical
     */
    public function setArrayValueIfChanged(array &$target, string $path, mixed $value): bool
    {
        if (!$path) {
            if ($target === $value) {
                return false;
            }
            $target = $value;

            return true;
        }

        // Strict comparison handles nested arrays and objects
        if (Arr::has($target, $path)) {
            if (Arr::get($target, $path) === $value) {
                return false;
            }
        }

        Arr::set($target, $path, $value);

        return true;
    }


    /**
     * Comprehensive conversion preserving exact keys for Models, Objects, and Arrays.
     *
     * @param  mixed  $data
     * @param  bool   $secure  (ignores $hidden in Model)
     *
     * @return mixed
     */
    public static function toArrayPreserveKeys(mixed $data, bool $secure = true): mixed
    {
        return match (true) {
            $data instanceof Model => self::handleEloquentModel($data, $secure),

            is_array($data) => array_map(fn(mixed $item): mixed => self::toArrayPreserveKeys($item, $secure),
                $data),

            $data instanceof Arrayable => self::toArrayPreserveKeys($data->toArray(), $secure),

            $data instanceof \JsonSerializable => self::toArrayPreserveKeys($data->jsonSerialize(), $secure),

            is_object($data) => self::toArrayPreserveKeys((array) $data, $secure),

            default => $data,
        };
    }

    /**
     * Extract attributes, relations, and appended computed properties.
     *
     * @param  Model  $model
     * @param  bool   $secure  (ignores $hidden in Model)
     *
     * @return array
     */
    private static function handleEloquentModel(Model $model, bool $secure = true): array
    {
        // 1. Get raw attributes and applied casts, but exclude hidden
        // array_flip turns ["password", "remember_token"]
        // the keys into ["password" => 0, "remember_token" => 1]
        //
        // attributesToArray() allows cast $casts
        $cleanAttributes = $secure ? array_diff_key($model->attributesToArray(), array_flip($model->getHidden())) : $model->attributesToArray();

        // 2. Resolve Accessors/Attributes defined via Attribute::make() or getXxxAttribute()
        // We iterate over the model's 'appends' to ensure custom logic is included
        $appends = self::getAppendedAttributes($model);

        // 3. Merge all data sources without triggering snake_case transformation
        $combined = array_merge($cleanAttributes,
            $appends,
            $model->getRelations());

        return array_map(fn(mixed $item): mixed => self::toArrayPreserveKeys($item, $secure),
            $combined);
    }

    /**
     * Dynamically resolve 'appends' to keep custom logic like 'user_profile' or 'CamelCaseProperty'.
     *
     * @param  Model  $model
     *
     * @return array
     */
    private static function getAppendedAttributes(Model $model): array
    {
        $appendedData = [];

        foreach ($model->getAppends() as $key) {
            $appendedData[$key] = $model->{$key};
        }

        return $appendedData;
    }

    /**
     * Short, URL-safe hash: sha256 (raw binary) encoded as URL-safe Base64 and cut to $length.
     * Base64 holds 6 bits per char, so 12 chars are 72 bits (a hex string would need 18 chars).
     * Same input gives the same hash. Without input a unique id is used (random result).
     *
     * @param  string|null  $input
     * @param  int          $length  max 43
     *
     * @return string
     */
    public function getShortSecHash(?string $input = null, int $length = 12): string
    {
        if ($input === null) {
            $input = uniqid('', true);
        }
        $rawHash = hash('sha256', $input, true); // Raw binary output
        $b64 = base64_encode($rawHash);
        $urlSafe = str_replace(['+', '/', '='], ['-', '_', ''], $b64); // Clean symbols

        return substr($urlSafe, 0, $length); // Truncate to desired length
    }

    /**
     * Replaces ${ENV_VAR} placeholders by env() values.
     * - Unknown env vars stay unchanged.
     * - Placeholders like ${{ANOTHER_FORMAT}} (see ParserService) are left completely untouched.
     *
     * @param  string  $subject
     *
     * @return string
     */
    public function parseEnv(string $subject): string
    {
        // (?<!\$)  -> Ensure no leading '$' before '${'
        // \${      -> Match literal '${'
        // ([A-Z0-9_]+) -> Match valid environment variable names strictly
        // }        -> Match literal '}'
        // (?<!})   -> Ensure no trailing '}' right after the match to prevent matching inner brackets of ${{...}}
        $pattern = '/(?<!\$)\${([A-Z0-9_]+)}(?!})/';

        return Str::replaceMatches($pattern, function (array $matches): string {
            // $matches[1] contains the clean variable name inside the brackets
            $envValue = env($matches[1]);

            // If the env variable does not exist, return the original full match string (${VAR})
            return $envValue !== null ? (string) $envValue : $matches[0];
        }, $subject);
    }

    /**
     * Parse a multidimensional array recursively and replace ${ENV_VAR} placeholders inside string values.
     *
     * @param  array  $data
     *
     * @return array
     */
    public function parseEnvArray(array $data): array
    {
        array_walk_recursive($data, function (mixed &$value): void {
            if (is_string($value)) {
                $value = $this->parseEnv($value);
            }
        });

        return $data;
    }

    /**
     * Renders a view once and caches the HTML (config 'system-base.cache.frontend.ttl').
     * Returns '' and logs an error if the view does not exist.
     *
     * @param  string  $cacheKey
     * @param  string  $viewPath
     *
     * @return string
     */
    public function getViewCache(string $cacheKey, string $viewPath): string
    {
        $method = __METHOD__;

        // hard cache view ...
        return app(CacheService::class)->rememberUseConfig($cacheKey, 'system-base.cache.frontend.ttl', function () use ($viewPath, $method) {
            if (view()->exists($viewPath)) {
                return view($viewPath)->render();
            }

            $this->error("Path not found: $viewPath", [$method]);

            return '';
        });
    }

    /**
     * Provides message box buttons (config 'message-boxes') for JavaScript by 'php_to_js'.
     * Uses a 2-step cache: 1) already added to php_to_js in this request, 2) getViewCache()
     *
     * @param  string|null  $objectKey  like 'user', 'media-item', 'notification-event', ... null for everything
     * @param  string|null  $category   like  'default', 'form', 'data-table', ... null for everything
     * @param  string|null  $task       like 'delete', 'rating', 'send-mail', ... null for everything
     *
     * @return void
     */
    public function provideMessageBoxButtons(?string $objectKey = null, ?string $category = null, ?string $task = null): void
    {
        foreach (config('message-boxes', []) as $configL1Key => $configL1Value) {

            if ($objectKey !== null && $objectKey !== $configL1Key) {
                continue;
            }

            foreach ($configL1Value as $configL2Key => $configL2Value) {

                if ($category !== null && $category !== $configL2Key) {
                    continue;
                }

                foreach ($configL2Value as $configL3Key => $configL3Value) {

                    if ($task !== null && $task !== $configL3Key) {
                        continue;
                    }

                    foreach (data_get($configL3Value, 'actions', []) as $action) {
                        $msgBoxKeyPath = 'messageBoxes.'.$action;
                        // already provided in this request?
                        if (app('php_to_js')->has($msgBoxKeyPath)) {
                            continue;
                        }

                        // 'module::button' or just 'button' (then system-base is used)
                        $actionParts = explode('::', $action);
                        if (count($actionParts) === 1) {
                            $actionParts = ['system-base', $actionParts[0]];
                        }
                        if (count($actionParts) === 2) {
                            $b = $this->getViewCache('message-box-button-'.$configL1Key.$configL2Key.$configL3Key.$action, $actionParts[0].'::inc.message-box.buttons.'.$actionParts[1]);

                            // set values like messageBoxes['system-base::cancel']
                            app('php_to_js')->addData($msgBoxKeyPath, $b);
                        }
                    }
                }
            }
        }
    }

}
