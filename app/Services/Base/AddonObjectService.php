<?php

namespace Modules\SystemBase\app\Services\Base;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Base Class for Addons.
 * Addon is like Module or Theme
 */
class AddonObjectService extends BaseService
{
    /**
     * Overwrite this!
     * Currently one them: 'module', 'theme'
     *
     * @var string
     */
    protected string $addonType = '';

    /**
     * Enable/Disable
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
     * @param  string  $path
     * @param  string  $itemName
     * @param  string  $generatorKey  config key for path definition (like 'config' or 'views')
     *
     * @return string
     */
    public static function getPath(string $path, string $itemName, string $generatorKey = ''): string
    {
        return '';
    }

    /**
     * Get the snake name of this item.
     *
     * @param  mixed  $item
     *
     * @return string
     */
    public static function getSnakeName(mixed $item): string
    {
        return Str::snake($item, '-');
    }

    /**
     * Get the snake name of this item.
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
     * Get split info: vendor (if present) and item name.
     *
     * @param  string  $itemIdentifier
     * @param  bool    $validate  don't set true if read originals like from composer.json
     *
     * @return array
     */
    public function getVendorInfo(string $itemIdentifier, bool $validate = false): array
    {
        if ($validate) {
            // correct snake symbols
            $itemIdentifier = str_replace('_', '-', $itemIdentifier);
        }

        $tmp = explode('/', $itemIdentifier);
        $v = config('mercy-dependencies.required.git.default_vendor', '');
        $n = $itemIdentifier;
        if (count($tmp) === 2) {
            $v = $tmp[0];
            $n = $tmp[1];
        }

        if ($validate) {
            // force snake and lower case first
            $n = Str::snake(lcfirst($n), '-');
        }

        $ng = $n;
        $mPrefix = '-'.$this->addonType;
        if (Str::endsWith($ng, $mPrefix)) {
            $n = Str::replaceEnd($mPrefix, '', $n);
        } else {
            $ng .= $mPrefix;
        }

        return [
            'vendor_name'                      => $v,
            $this->addonType.'_snake_name'     => $n,
            $this->addonType.'_snake_name_git' => $ng,
        ];
    }

    /**
     * Must be overwritten to make sense ...
     *
     * @param  mixed  $item
     *
     * @return array{
     *     is_installed:bool,
     *     is_enabled:bool,
     *     name:string,
     *     priority:int,
     *     studly_name:string,
     *     snake_name:string,
     *     path:string,
     *     composer_json:string,
     * }
     */
    public function getItemInfo(mixed $item): array
    {
        return [
            'is_installed'           => true,
            'is_enabled'             => false,
            'name'                   => '',
            'priority'               => 1100,
            'studly_name'            => '',
            'snake_name'             => '',
            'path'                   => '',
            'composer_json'          => [],
            $this->addonType.'_json' => [],
        ];
    }

    /**
     * Get a collection of all modules.
     *
     * @return Collection
     */
    public function getItemsCollection(): Collection
    {
        return collect();
    }

    /**
     * @param  bool  $enabledOnly
     *
     * @return Collection
     */
    public function getItemInfoList(bool $enabledOnly = true): Collection
    {
        $itemList = collect();

        return $itemList;
    }
}