<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\AddonObjectService;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Nwidart\Modules\Module;

/**
 * Module helpers based on nwidart/laravel-modules.
 * As singleton: app('system_base_module').
 */
class ModuleService extends AddonObjectService
{
    /**
     * @var string
     */
    protected string $addonType = 'module';

    /**
     * Enables/disables a module in the nwidart statuses file (modules_statuses.json).
     *
     * @param  string  $itemName            module studly name
     * @param  bool    $status
     * @param  bool    $ignoreIfItemExists  true: keep the status if the module is already listed
     *
     * @return bool  false if the statuses file does not exist or is not writable
     */
    public function setStatus(string $itemName, bool $status, bool $ignoreIfItemExists = false): bool
    {
        $moduleStatusFile = config('modules.activators.file.statuses-file');
        if (!$moduleStatusFile || !file_exists($moduleStatusFile)) {
            return false;
        }

        $list = json_decode((string) file_get_contents($moduleStatusFile), true) ?: [];

        if ($ignoreIfItemExists && isset($list[$itemName])) {
            return true;
        }

        $list[$itemName] = $status;

        return file_put_contents($moduleStatusFile, json_encode($list, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Path inside a module like ".../Modules/MyModule/{generator path}/{path}".
     *
     * @param  string  $path
     * @param  string  $itemName      (Module StudlyName)
     * @param  string  $generatorKey  config key for path definition (like 'config' or 'views')
     * @param  string  $rootPath      if set use this as project root (to deploy to external projects)
     *
     * @return string
     */
    public static function getPath(string $path, string $itemName, string $generatorKey = '', string $rootPath = ''): string
    {
        if ($rootPath) {
            $result = $rootPath.DIRECTORY_SEPARATOR.'Modules'.DIRECTORY_SEPARATOR.$itemName;
        } else {
            $result = ModuleFacade::getPath().DIRECTORY_SEPARATOR.$itemName;
        }

        if ($generatorKey) {
            if ($configPath = config('modules.paths.generator.'.$generatorKey.'.path')) {
                $result .= DIRECTORY_SEPARATOR.$configPath;
            }
        }

        if ($path) {
            $result .= DIRECTORY_SEPARATOR.$path;
        }

        return $result;
    }

    /**
     * Class name like "Modules\MyModule\app\Models\MyModel".
     * The namespace part is taken from the nwidart generator path config.
     *
     * @param  string  $itemName      (Module StudlyName)
     * @param  string  $generatorKey  nwidart config key for the path (like 'model', 'config' or 'views')
     * @param  string  $classPath     class name (or sub path) inside that folder
     *
     * @return string
     */
    public static function getNamespace(string $itemName, string $generatorKey, string $classPath): string
    {
        $path = '';
        if ($generatorKey) {
            if ($configPath = config('modules.paths.generator.'.$generatorKey.'.path')) {
                $path = $configPath;
            }
        }

        $path = str_replace('/', "\\", $path);

        return sprintf('%s\%s\%s\%s', config('modules.namespace', 'Modules'), $itemName, $path, $classPath);
    }

    /**
     * Get the snake name of the module.
     * Snake names have '-' notation by default!
     *
     * @param  Module|string  $item
     * @param  string         $delimiter
     *
     * @return string
     */
    public static function getSnakeName(mixed $item, string $delimiter = '-'): string
    {
        if ($item instanceof Module) {
            $item = $item->getStudlyName();
        }

        return parent::getSnakeName($item, $delimiter);
    }

    /**
     * Get the studly name of the module.
     *
     * @param  Module|string  $item
     *
     * @return string
     */
    public static function getStudlyName(mixed $item): string
    {
        if ($item instanceof Module) {
            $item = $item->getStudlyName();
        }

        return parent::getStudlyName($item);
    }

    /**
     * Get the snake name of a model (always with '-').
     *
     * @param  string  $model
     *
     * @return string
     */
    public static function getModelSnakeName(string $model): string
    {
        return parent::getSnakeName($model, '-');
    }

    /**
     * Get ordered and enabled modules.
     *
     * @param  bool  $reverse       backward sort by priority
     * @param  bool  $inclusiveApp  add item null for app
     *
     * @return array<?Module>
     */
    public static function getOrderedEnabledModules(bool $reverse = false, bool $inclusiveApp = false): array
    {
        $list = ModuleFacade::getOrdered($reverse ? 'desc' : 'asc');
        if ($inclusiveApp) {
            if ($reverse) {
                // add app (null) to start
                array_unshift($list, null);
            } else {
                // add app (null) to end
                $list[] = null;
            }
        }

        $finalList = [];
        /** @var ?Module $module */
        foreach ($list as $module) {
            if (!$module || $module->isEnabled()) {
                $finalList[] = $module;
            }
        }

        return $finalList;
    }

    /**
     * If a callback returns false, the loop will be stopped!
     *
     * @param  callable  $callback      parameters like callback(?Module $module), null is the app
     * @param  bool      $reverse       backward sort by priority
     * @param  bool      $inclusiveApp  add item null for app
     *
     * @return bool True if all modules returned true. Otherwise, false.
     */
    public static function runOrderedEnabledModules(callable $callback, bool $reverse = false, bool $inclusiveApp = false): bool
    {
        foreach (static::getOrderedEnabledModules($reverse, $inclusiveApp) as $module) {
            if (!$callback($module)) {
                return false;
            }
        }

        return true;
    }

    /**
     * True if an ENABLED module with this name exists.
     *
     * @param  string  $name  name in studly or snake case (with '-' or '_')
     *
     * @return bool
     */
    public function moduleExists(string $name): bool
    {
        $found = false;
        $this->runOrderedEnabledModules(function (?Module $module) use ($name, &$found) {
            if (($this->getStudlyName($module) === $name)
                || ($this->getSnakeName($module) === $name)
                // bound by '_' is deprecated?
                || ($this->getSnakeName($module, '_') === $name)
            ) {
                $found = true;

                return false; // stop module loop
            }

            return true;
        });

        return $found;
    }

    /**
     * Get module info and returns prepared data.
     * If the module exists, composer.json and module.json are read and combined with the data.
     * Otherwise, the data is calculated from the name (to prepare/create/clone it).
     * The result also contains the keys of getVendorInfo().
     *
     * @param  string|Module  $item
     * @param  string         $destinationRootPath  if given, no local checks will be made, just deploy into this project root path
     *
     * @return array
     */
    public function getItemInfo(mixed $item, string $destinationRootPath = ''): array
    {
        $result = parent::getItemInfo($item, $destinationRootPath);

        $moduleLongName = $item ?? '';

        /** @var Module $item */
        if (!$destinationRootPath && !($item instanceof Module)) {
            $vendorInfo = $this->getVendorInfo($moduleLongName, true);
            $moduleToFind = data_get($vendorInfo, 'module_snake_name');
            if (!($item = ModuleFacade::find(Str::studly($moduleToFind)))) {
                $this->warning(sprintf("Module '%s' currently not installed.", $moduleToFind), [__METHOD__]);
                $item = null;
                $result['is_installed'] = false;
            }
        }

        if (!$destinationRootPath && $item) { // is object?
            $result['is_enabled'] = $item->isEnabled();
            $result['name'] = $this->getStudlyName($item);
            $result['studly_name'] = $this->getStudlyName($item);
            $result['snake_name'] = $this->getSnakeName($result['studly_name']);
            $moduleLongName = $result['snake_name']; // inclusive vendor if composer found
            //$result['path'] = $item->getPath();
            $result['path'] = $this->getPath('', $result['name'], rootPath: $result['path']);
            $result['priority'] = (int) $item->getPriority(); // Module::priority is returning a string ...

            // read composer.json
            $composerPath = $result['path'].'/composer.json';
            if (file_exists($composerPath)) {
                $result['composer_json'] = file_get_contents($composerPath);
                $result['composer_json'] = json_decode($result['composer_json'], true);
                if ($result['composer_json']) {
                    $moduleLongName = data_get($result['composer_json'], 'name');
                }
            }
            $vendorInfo = $this->getVendorInfo($moduleLongName);

            // read module.json
            $moduleJsonPath = $result['path'].'/module.json';
            if (file_exists($moduleJsonPath)) {
                $result['module_json'] = file_get_contents($moduleJsonPath);
                $result['module_json'] = json_decode($result['module_json'], true);
            }

        } else { // is named
            $vendorInfo = $this->getVendorInfo($moduleLongName, true);
            $result['snake_name'] = data_get($vendorInfo, 'module_snake_name');
            $result['name'] = Str::studly($result['snake_name']);
            $result['studly_name'] = $result['name'];
            $result['path'] = $this->getPath('', $result['name'], rootPath: $result['path']);
        }

        return array_merge($result, $vendorInfo);
    }

    /**
     * Get a collection of all modules.
     *
     * @return Collection
     */
    public function getItemsCollection(): Collection
    {
        return collect(ModuleFacade::all());
    }

    /**
     * Get info of all found modules.
     * Modules are ordered by priority.
     *
     * @param  bool  $enabledOnly
     *
     * @return Collection
     */
    public function getItemInfoList(bool $enabledOnly = true): Collection
    {
        $itemList = collect();

        // priority is not a property, so sort() is a callable
        $collection = $this->getItemsCollection()
            ->sort(function (Module $a, Module $b) {
                return (int) $a->getPriority() - (int) $b->getPriority();
            });

        // enable is not a property, so where() is a callable
        if ($enabledOnly) {
            $collection = $collection->where(function (Module $m) {
                return $m->isEnabled();
            });
        }

        // read module info of all modules of the sorted list
        /** @var Module $module */
        foreach ($collection as $module) {
            $itemList->add($this->getItemInfo($module));
        }

        return $itemList;
    }

    /**
     * All classes (files) directly inside a module folder like app/Models as key => class name.
     * The key is the snake name with '-' like "model-my-model" (with $usePrefix) or "my-model".
     *
     * @param  string  $itemName      module studly name
     * @param  string  $generatorKey  nwidart config key for the path (like 'model')
     * @param  bool    $usePrefix     prefix the key with "$generatorKey-"
     * @param  array   $blackList     can contain the key, the simple class name or the full class name
     *
     * @return array
     */
    public static function getAllClassesInPath(string $itemName, string $generatorKey, bool $usePrefix = true, array $blackList = []): array
    {
        $declaredClassesInNamespace = [];
        $path = self::getPath('', $itemName, $generatorKey);
        if (is_dir($path) && ($scanDirList = scandir($path))) {
            foreach ($scanDirList as $file) {
                $fullPath = $path.DIRECTORY_SEPARATOR.$file;
                if (is_file($fullPath)) {
                    $pi = pathinfo($fullPath);
                    if ($modelName = $pi['filename']) {
                        $key = self::getModelSnakeName($modelName);
                        if ($usePrefix) {
                            $key = $generatorKey.'-'.$key;
                        }
                        $fullModelName = self::getNamespace($itemName, $generatorKey, $modelName);
                        if (in_array($key, $blackList) || in_array($modelName, $blackList) || in_array($fullModelName, $blackList)) {
                            continue;
                        }
                        $declaredClassesInNamespace[$key] = $fullModelName;
                    }
                }
            }
        }

        return $declaredClassesInNamespace;
    }

    /**
     * All container binding names starting with "$generatorKey-" like "model-user".
     *
     * @param  string  $generatorKey
     *
     * @return array
     */
    public static function getAllBindings(string $generatorKey = 'model'): array
    {
        $result = [];
        foreach (array_keys(app()->getBindings()) as $binding) {
            if (Str::startsWith($binding, $generatorKey.'-')) {
                $result[] = $binding;
            }
        }

        return $result;
    }

}