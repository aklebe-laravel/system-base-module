<?php

namespace Modules\SystemBase\app\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\BaseService;
use Nwidart\Modules\Facades\Module;

class SystemService extends BaseService
{
    const dateIsoFormat8601 = 'Y-m-d H:i:s';

    const SortModeNone = 0x0000;
    const SortModeByKey = 0x0001;
    const SortModeByValue = 0x0002;
    const SortModeAsc = 0x0100;
    const SortModeDesc = 0x0200;

    /**
     *
     */
    const UNDEFINED_CONTENT = '##__UNDEFINED_CONTENT__##';

    /**
     * @var array
     */
    protected array $uniqueCounters = [];

    /**
     * Try always to force an array result.
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
     * Unlike toArray() this method will not force scalars into array and have the following benefits:
     * 1) Faster than json_decode(json_encode())
     * 2) No camel to snake convert
     *
     * @param  mixed  $data
     * @param  int  $maxDeep
     *
     * @return array|mixed
     */
    public function toArrayRaw(mixed $data, int $maxDeep = 2): mixed
    {
        if ($maxDeep >= 1) {
            if (is_array($data) || is_object($data)) {
                $result = [];
                foreach ($data as $key => $value) {
                    $result[$key] = (is_array($value) || is_object($value)) ? $this->toArrayRaw($value,
                        $maxDeep - 1) : $value;
                }

                return $result;
            }
        }

        return $data;
    }

    /**
     * Use $forceOverride=true to force an inheritance behaviour.
     * If $ignoreNull is true and the new value is null, the destination will not be overwritten.
     * But in this case you can force to override keys with $butForceOverrideKeys.
     * $ignoreNull will not work if $forceOverride = true.
     *
     * @param  array  $destination
     * @param  array  $source
     * @param  bool  $forceOverride  its like unconditional inheritance
     * @param  bool  $ignoreNull
     * @param  array  $butForceOverrideKeys
     *
     * @return array
     */
    public static function arrayMergeRecursiveDistinct(array &$destination, array &$source, bool $forceOverride = false, bool $ignoreNull = true, array $butForceOverrideKeys = []): array
    {
        foreach ($source as $key => &$value) {
            if (is_array($value) && isset($destination[$key]) && is_array($destination[$key])) {
                $destination[$key] = self::arrayMergeRecursiveDistinct($destination[$key], $value, $forceOverride);
            } else {
                if ($forceOverride || (!$ignoreNull) || ($value !== null) || in_array($key, $butForceOverrideKeys)) {
                    $destination[$key] = $value;
                }
            }
        }

        return $destination;
    }

    /**
     * @param  array  $a
     * @param  array  $b
     *
     * @return bool
     */
    public function arrayCompareIsBInA(array &$a, array &$b): bool
    {
        foreach ($a as $k => &$aItem) {
            // if (!isset($b[$k]) && !is_null($b[$k])) {
            if (!$this->hasData($b, $k)) {
                return false;
            }
            if (is_array($aItem)) {
                if (!$this->arrayCompareIsEqual($aItem, $b[$k])) {
                    return false;
                }
            } elseif (is_object($aItem)) {
                if ($aItem !== $b[$k]) {
                    return false;
                }
            } else {
                if ($aItem !== $b[$k]) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
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
     * Copies root keys in $whitelistArray from $arraySrc to $arrayDest, but no arrays.
     * Useful to inherit parent key:values linke disabled:false in a nested array.
     *
     * @param  array  $arrayDest
     * @param  array  $arraySrc
     * @param  array  $whitelistArray
     *
     * @return array
     */
    public function arrayRootCopyWhitelistedNoArrays(array &$arrayDest, array $arraySrc, array $whitelistArray): array
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
     * @param  iterable|null  $list
     * @param  mixed|null  $valueProperty  special meaning '[key]'
     * @param  string|null  $keyProperty  special meaning '[key]'
     * @param  array  $first
     * @param  int  $sortMode
     *
     * @return array
     */
    public function toHtmlSelectOptions(?iterable $list, mixed $valueProperty = null, string $keyProperty = null, array $first = [], int $sortMode = self::SortModeNone): array
    {
        $options = [];
        if ($list) {

            foreach ($list as $k => $v) {

                if ($keyProperty === '[key]') {
                    $key = $k;
                } else {
                    $key = ($keyProperty === null) ? $k : data_get($v, $keyProperty);
                }

                // Mehrere Values?
                if (is_array($valueProperty)) {
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

            if ($sortMode & self::SortModeByKey) {
                if ($sortMode & self::SortModeAsc) {
                    asort($options, SORT_FLAG_CASE);
                } else {
                    arsort($options, SORT_FLAG_CASE);
                }
            }
            if ($sortMode & self::SortModeByValue) {
                if ($sortMode & self::SortModeAsc) {
                    sort($options, SORT_FLAG_CASE);
                } else {
                    rsort($options, SORT_FLAG_CASE);
                }
            }

        }

        if ($first) {
            $options = $first + $options;
        }

        return $options;
    }

    /**
     * Diese Methode prüft die Gleichheit zweier Strings unabhängig von Groß-/Kleinschreibung und Umlauten.
     * Bsp.:
     * - strCaseCompare('de', 'DE') = true
     * - strCaseCompare('über', 'ÜBER') = true
     *
     * @param $stringA
     * @param $stringB
     *
     * @return bool
     */
    public function strCaseCompare($stringA, $stringB): bool
    {
        return (strcoll(Str::lower($stringA), Str::lower($stringB)) === 0);
    }

    /**
     * Diese Methode statt is_callable() benutzen um zu verhindern, dass auch strings ausgewertet
     * und als Funktionen umgewandelt werden (zB.: 'max').
     *
     * @param $f
     *
     * @return bool
     */
    public function isCallableClosure($f): bool
    {
        return ($f instanceof \Closure);
    }

    /**
     * @param  string  $classNameWithoutNamespace
     *
     * @return Model
     */
    public function getEloquentModel(string $classNameWithoutNamespace): Model
    {
        $moduleClass = $this->findModuleClass($classNameWithoutNamespace);

        return App::make($moduleClass);
    }

    /**
     * @param  string  $classNameWithoutNamespace
     *
     * @return Builder
     */
    public function getEloquentModelBuilder(string $classNameWithoutNamespace): Builder
    {
        return $this->getEloquentModel($classNameWithoutNamespace)->query();
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
     * @param  mixed  $price
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
     * @param $time
     *
     * @return string
     */
    public function formatDate($time): string
    {
        $timeLocale = \Illuminate\Support\Carbon::parse($time)->locale('de');
        $d = $timeLocale->dayName.', '.$timeLocale->translatedFormat('d.m.Y');

        return $d;
    }

    /**
     * @param $time
     *
     * @return string
     */
    public function formatTime($time): string
    {
        $timeLocale = \Illuminate\Support\Carbon::parse($time)->locale('de');
        $d = $timeLocale->translatedFormat('H:i').'h ';

        return $d;
    }

    /**
     * @param $time
     *
     * @return string
     */
    public function formatTimeDiff($time): string
    {
        $timeLocale = \Illuminate\Support\Carbon::parse($time)->locale('de');
        $d = $timeLocale->shortAbsoluteDiffForHumans();

        return $d;
    }

    /**
     * @param  float  $startTime
     *
     * @return float
     */
    public function getExecutionTime(float $startTime): float
    {
        return microtime(true) - $startTime;
    }

    /**
     * @param  string  $name
     * @param  float  $startTime
     *
     * @return void
     */
    public function logExecutionTime(string $name, float $startTime): void
    {
        $this->debug('Script "'.$name.'" execution time: '.number_format($this->getExecutionTime($startTime), 2, '.', '').' sec');
    }

    /**
     * Determine whether $subject matching one if the patterns in $patternList.
     *
     * @param  string  $subject
     * @param  array  $patternList
     * @param  string  $addDelimiters
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
     * Find module models before app models, so modules have the power to overwrite.
     *
     * @param  string  $className
     * @param  string  $generatorKey  config key for path definition (like 'models', 'config' or 'views')
     * @param  bool  $returnInfoData
     * @param  string  $forceModule
     *
     * @return string|array
     */
    public function findModuleClass(string $className, string $generatorKey = 'model', bool $returnInfoData = false, string $forceModule = ''): string|array
    {
        $ttlDefault = config('system-base.cache.default_ttl', 1);
        $ttl = config('system-base.cache.object.signature.ttl', $ttlDefault);

        return Cache::remember("findModule_{$forceModule}_{$className}_{$generatorKey}_".($returnInfoData ? '1' : '0'),
            $ttl, function () use ($className, $generatorKey, $returnInfoData, $forceModule) {

                /** @var ModuleService $moduleService */
                $moduleService = app(ModuleService::class);

                $modules = Module::allEnabled();
                /** @var \Nwidart\Modules\Module $module */
                foreach ($modules as $module) {

                    if ($forceModule && ($module->getName() !== $forceModule)) {
                        continue;
                    }

                    $currentClassNameGenerated = ModuleService::getNamespace($module->getStudlyName(), $generatorKey,
                        $className);

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
                    } catch (\Exception) {
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
                } catch (\Exception) {
                }

                if ($returnInfoData) {
                    return [];
                }

                return "";
            });

    }

    /**
     * @param  string  $modelClass
     *
     * @return string
     */
    public function getModelTable(string $modelClass): string
    {
        // find table name to avoid ambiguous columns
        $ttlDefault = config('system-base.cache.default_ttl', 1);
        $ttl = config('system-base.cache.db.signature.ttl', $ttlDefault);

        return Cache::remember('table_name_of_model_'.$modelClass, $ttl, function () use ($modelClass) {
            return app($modelClass)->getTable();
        });
    }

    /**
     * @param  string  $className
     * @param  string  $generatorKey  config key for path definition (like 'models', 'config' or 'views')
     * @param  string  $forceModule
     *
     * @return string
     * @throws \Exception
     */
    public function findLivewire(string $className, string $generatorKey = '', string $forceModule = ''): string
    {
        // Get class infos like module ...
        if (!($classInfo = $this->findModuleClass($className, $generatorKey, true, $forceModule))) {
            throw new \Exception('Missing livewire component: '.$className);
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
        $ttlDefault = config('system-base.cache.default_ttl', 1);
        $ttl = config('system-base.cache.translations.ttl', $ttlDefault);

        return Cache::remember('system_base_translations_'.json_encode($langList), $ttl, function () use ($langList) {
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
     * This should use instead of 'instanceof' check when testing traits.
     *
     * @param  mixed  $instance  instance or class name we want to check
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
     * Check dot path exists in object/array
     *
     * @param  mixed  $object
     * @param         $keyPath
     *
     * @return bool
     */
    public function hasData(mixed $object, $keyPath): bool
    {
        return (data_get($object, $keyPath, self::UNDEFINED_CONTENT) !== self::UNDEFINED_CONTENT);
    }

    /**
     * get cached columns
     *
     * @param  string  $tableName
     *
     * @return array
     */
    public function getDbColumns(string $tableName): array
    {
        $ttlDefault = config('system-base.cache.default_ttl', 1);
        $ttl = config('system-base.cache.db.signature.ttl', $ttlDefault);

        return Cache::remember('db_columns_from_table_'.$tableName, $ttl, function () use ($tableName) {
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
        $ttlDefault = config('system-base.cache.default_ttl', 1);
        $ttl = config('system-base.cache.object.signature.ttl', $ttlDefault);

        return Cache::remember('current_user_class_name', $ttl, function () {
            return app(User::class)::class;
        });
    }

    /**
     * Increment the unique counter and return it.
     * Can be used for frontend html ids.
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

}
