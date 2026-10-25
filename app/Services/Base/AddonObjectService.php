<?php

namespace Modules\SystemBase\app\Services\Base;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Base class for addon services.
 * An addon is a module or a theme, see ModuleService and ThemeService.
 */
class AddonObjectService extends BaseService
{
    /**
     * Overwrite this!
     * Currently one of them: 'module', 'theme'
     *
     * @var string
     */
    protected string $addonType = '';

    /**
     * Default vendor. If set, it wins over a vendor given in getVendorInfo().
     *
     * @var string
     */
    public string $vendor = '';

    /**
     * Enable/Disable an addon. Overwrite this, the base implementation does nothing.
     *
     * @param  string  $itemName
     * @param  bool    $status
     * @param  bool    $ignoreIfItemExists
     *
     * @return bool
     */
    public function setStatus(string $itemName, bool $status, bool $ignoreIfItemExists = false): bool
    {
        return false;
    }

    /**
     * Path inside an addon. Overwrite this, the base implementation returns ''.
     *
     * @param  string  $path
     * @param  string  $itemName
     * @param  string  $generatorKey  config key for path definition (like 'config' or 'views')
     * @param  string  $rootPath      if set use this as project root (to deploy to external projects)
     *
     * @return string
     */
    public static function getPath(string $path, string $itemName, string $generatorKey = '', string $rootPath = ''): string
    {
        return '';
    }

    /**
     * Get the snake name of this item.
     *
     * @param  mixed   $item
     * @param  string  $delimiter
     *
     * @return string
     */
    public static function getSnakeName(mixed $item, string $delimiter = '-'): string
    {
        return Str::snake($item, $delimiter);
    }

    /**
     * Get the studly name of this item.
     *
     * @param  mixed  $item
     *
     * @return string
     */
    public static function getStudlyName(mixed $item): string
    {
        return Str::studly($item);
    }

    /**
     * Splits an identifier like "vendor/my-module" into vendor and names.
     *
     * Example for addon type 'module' and "acme/system_base":
     * [
     *     'vendor_name'           => 'acme' ($this->vendor wins if set),
     *     'module_snake_name'     => 'system-base',
     *     'module_snake_name_git' => 'system-base-module',
     * ]
     * A trailing "-module"/"-theme" is removed from the snake name and always added to the git name.
     *
     * @param  string  $itemIdentifier
     * @param  bool    $validate  normalizes the name to lower snake case with '-'.
     *                            Don't set true for originals like the name from composer.json.
     *
     * @return array
     */
    public function getVendorInfo(string $itemIdentifier, bool $validate = false): array
    {
        if ($validate) {
            // '_' is not allowed, use '-' instead
            $itemIdentifier = str_replace('_', '-', $itemIdentifier);
        }

        $tmp = explode('/', $itemIdentifier);
        $v = $this->vendor;
        $n = $itemIdentifier;
        if (count($tmp) === 2) {
            $v = $this->vendor ?: $tmp[0]; // use given vendor first
            $n = $tmp[1];
        }

        if ($validate) {
            // force snake and lower case first
            $n = Str::snake(lcfirst($n), '-');
        }

        // name for git always ends with '-module' or '-theme', the snake name never
        $ng = $n;
        $typeSuffix = '-'.$this->addonType;
        if (Str::endsWith($ng, $typeSuffix)) {
            $n = Str::replaceEnd($typeSuffix, '', $n);
        } else {
            $ng .= $typeSuffix;
        }

        return [
            'vendor_name'                      => $v,
            $this->addonType.'_snake_name'     => $n,
            $this->addonType.'_snake_name_git' => $ng,
        ];
    }

    /**
     * Info about an addon. Must be overwritten to make sense, this is the default structure.
     *
     * @param  mixed   $item
     * @param  string  $destinationRootPath  if given, no local checks will be made, just deploy into this project root path
     *
     * @return array{
     *     is_installed:bool,
     *     is_enabled:bool,
     *     name:string,
     *     priority:int,
     *     studly_name:string,
     *     snake_name:string,
     *     path:string,
     *     composer_json:array,
     * }
     */
    public function getItemInfo(mixed $item, string $destinationRootPath = ''): array
    {
        return [
            'is_installed'           => true,
            'is_enabled'             => false,
            'name'                   => '',
            'priority'               => 1100,
            'studly_name'            => '',
            'snake_name'             => '',
            'path'                   => $destinationRootPath,
            'composer_json'          => [],
            $this->addonType.'_json' => [],
        ];
    }

    /**
     * Get a collection of all addons of this type. Overwrite this.
     *
     * @return Collection
     */
    public function getItemsCollection(): Collection
    {
        return collect();
    }

    /**
     * Get getItemInfo() of all addons of this type. Overwrite this.
     *
     * @param  bool  $enabledOnly
     *
     * @return Collection
     */
    public function getItemInfoList(bool $enabledOnly = true): Collection
    {
        return collect();
    }
}