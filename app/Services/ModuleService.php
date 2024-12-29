<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\AddonObjectService;
use Nwidart\Modules\Facades\Module as ModuleFacade;
use Nwidart\Modules\Module;

class ModuleService extends AddonObjectService
{
    /**
     * Overwrite this!
     * Currently one them: 'module', 'theme'
     *
     * @var string
     */
    protected string $addonType = 'module';

    /**
     * Enable/Disable
     *
     * @param  string  $itemName
     * @param  bool  $status
     * @param  bool  $ignoreIfItemExists
     *
     * @return bool
     */
    public function setStatus(string $itemName, bool $status, bool $ignoreIfItemExists = false): bool
    {
        // enable the module
        $moduleStatusFile = config('modules.activators.file.statuses-file');
        if (file_exists($moduleStatusFile)) {
            $list = json_decode(file_get_contents($moduleStatusFile), true);

            if ($ignoreIfItemExists && isset($list[$itemName])) {
                return true;
            }

            $list[$itemName] = $status;
            if (file_put_contents($moduleStatusFile, json_encode($list, JSON_PRETTY_PRINT)) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $path
     * @param  string  $itemName  (Module StudlyName)
     * @param  string  $generatorKey  config key for path definition (like 'config' or 'views')
     *
     * @return string
     */
    public static function getPath(string $path, string $itemName, string $generatorKey = ''): string
    {
        $result = ModuleFacade::getPath().DIRECTORY_SEPARATOR.$itemName;

        if ($generatorKey) {
            if ($configPath = config('modules.paths.generator.'.$generatorKey.'.path')) {
                $result .= DIRECTORY_SEPARATOR.$configPath;
            }
        }

        if ($path) {
            $result .= '/'.$path;
        }

        return $result;
    }

    /**
     * @param  string  $itemName  (Module StudlyName)
     * @param  string  $generatorKey  config key for path definition (like 'models', 'config' or 'views')
     * @param  string  $classPath
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
        $path = sprintf('%s\%s\%s\%s', config('modules.namespace', 'Modules'), $itemName, $path, $classPath);

        return $path;
    }


    /**
     * Get the snake name of the module.
     * Snake names have '-' notation by default!
     *
     * @param  Module|string  $item
     *
     * @return string
     */
    public static function getSnakeName(mixed $item): string
    {
        if ($item instanceof Module) {
            $item = $item->getStudlyName();
        }

        return parent::getSnakeName($item, '-');
    }

    /**
     * Get the snake name of a model.
     *
     * @param  string  $model
     *
     * @return string
     */
    public static function getModelSnakeName(string $model): string
    {
        // return Str::snake($model, '-');
        return parent::getSnakeName($model, '-');
    }

    /**
     * If a callback returns false, the loop will be stopped!
     *
     * @param  callable  $callback  parameters like callback(!Module $module)
     * @param  bool  $reverse  backward sort by priority
     * @param  bool  $inclusiveApp  add item null for app
     *
     * @return bool True if all modules returned true. Otherwise, false.
     */
    public static function runOrderedEnabledModules(callable $callback, bool $reverse = false, bool $inclusiveApp = false): bool
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
        /** @var ?Module $module */
        foreach ($list as $module) {
            if (!$module || $module->isEnabled()) {
                if (!$callback($module)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  string  $name  name in studly, lower or snake case
     *
     * @return bool
     */
    public function moduleExists(string $name): bool
    {
        $found = false;
        $this->runOrderedEnabledModules(function (?Module $module) use ($name, &$found) {
            if (($module->getStudlyName() === $name) || ($module->getSnakeName() === $name) || ($module->getLowerName() === $name)) {
                $found = true;

                return false; // stop module loop
            }

            return true;
        });

        return $found;
    }

    /**
     * Get module info and returns prepared data.
     * If module already exists, composer.json and module.json will read and data will combined with.
     * Otherwise, data will be calculated (to prepare/create/clone it)
     *
     * @param  string|Module  $item
     *
     * @return array
     */
    public function getItemInfo(mixed $item): array
    {
        $result = parent::getItemInfo($item);

        $moduleLongName = $item ?? '';

        /** @var Module $item */
        if (!($item instanceof Module)) {
            $vendorInfo = $this->getVendorInfo($moduleLongName, true);
            $moduleToFind = data_get($vendorInfo, 'module_snake_name');
            if (!($item = ModuleFacade::find(Str::studly($moduleToFind)))) {
                $this->warning(sprintf("Module '%s' currently not installed.", $moduleToFind), [__METHOD__]);
                $item = null;
                $result['is_installed'] = false;
            }
        }

        if ($item) { // is object?
            $result['is_enabled'] = $item->isEnabled();
            $result['name'] = $item->getStudlyName();
            $result['studly_name'] = $item->getStudlyName();
            $result['snake_name'] = $this->getSnakeName($result['studly_name']);
            $moduleLongName = $result['snake_name']; // inclusive vendor if composer found
            $result['path'] = $item->getPath();
            $result['priority'] = (int)$item->getPriority(); // Module::priority is returning a string ...

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
            $result['path'] = $this->getPath('', $result['name']);
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
        $collection = $this->getItemsCollection()->sort(function (Module $a, Module $b) {
            return (int)$a->getPriority() - (int)$b->getPriority();
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
     * @param  string  $itemName
     * @param  string  $generatorKey
     * @param  bool  $usePrefix
     * @param  array  $blackList  can contains alias or simple model name or full model name
     *
     * @return array
     */
    public static function getAllClassesInPath(string $itemName, string $generatorKey, bool $usePrefix = true, array $blackList = []): array
    {
        $declaredClassesInNamespace = [];
        $path = self::getPath('', $itemName, $generatorKey);
        if ($scanDirList = scandir($path)) {
            foreach ($scanDirList as $file) {
                $fullPath = $path.'/'.$file;
                if (is_file($fullPath)) {
                    $pi = pathinfo($fullPath);
                    if ($modelName = $pi['filename']) {
                        //$key = Str::snake($modelName, '_');
                        $key = self::getModelSnakeName($modelName, '_');
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