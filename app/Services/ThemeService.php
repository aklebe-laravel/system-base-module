<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\AddonObjectService;
use Nwidart\Modules\Module;
use Shipu\Themevel\Facades\Theme;

class ThemeService extends AddonObjectService
{
    /**
     * Overwrite this!
     * Currently one them: 'module', 'theme'
     *
     * @var string
     */
    protected string $addonType = 'theme';

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
        return true;
    }

    /**
     * @param  string  $path
     * @param  string  $itemName
     * @param  string  $generatorKey  config key for path definition (like 'config' or 'views')
     *
     * @return string
     */
    public static function getPath(string $path = '', string $itemName = '', string $generatorKey = ''): string
    {
        if (!$itemName) {
            $itemName = self::getCurrentTheme();
        }

        if (!($result = config('theme.theme_path').'/'.$itemName)) {
            return '';
        }

        if ($path) {
            $result .= '/'.$path;
        }

        return $result;
    }

    /**
     * @param  string  $themeName
     *
     * @return string
     */
    public function getThemeParent(string $themeName = ''): string
    {
        if (!$themeName) {
            $themeName = $this->getCurrentTheme();
        }

        return Theme::getThemeInfo($themeName)->get('parent');
    }

    /**
     * @return string
     */
    public static function getCurrentTheme(): string
    {
        return current_theme();
    }

    /**
     * @param  string  $path
     * @param  string  $themeName
     * @param  int     $directoryDeep
     * @param  array   $regexWhitelist
     * @param  array   $regexBlacklist
     * @param  string  $addDelimiters
     *
     * @return array
     */
    public function getFilesFromTheme(string $path = '', string $themeName = '', int $directoryDeep = 0, array $regexWhitelist = [], array $regexBlacklist = [], string $addDelimiters = ''): array
    {
        if (!$themeName) {
            $themeName = $this->getCurrentTheme();
        }

        $result = [];

        if ($parentTheme = $this->getThemeParent($themeName)) {
            $result = $this->getFilesFromTheme($path, $parentTheme, $directoryDeep, $regexWhitelist, $regexBlacklist, $addDelimiters);
        }

        return array_merge($result, $this->getFilesInFolder($this->getPath($path, $themeName), $directoryDeep, $regexWhitelist, $regexBlacklist, $addDelimiters));
    }

    /**
     * @param  string  $path
     * @param  int     $directoryDeep
     * @param  array   $regexWhitelist
     * @param  array   $regexBlacklist
     * @param  string  $addDelimiters
     *
     * @return array
     */
    public function getFilesInFolder(string $path, int $directoryDeep = 0, array $regexWhitelist = [], array $regexBlacklist = [], string $addDelimiters = ''): array
    {
        $files = [];
        if (is_dir($path)) {

            app('system_base_file')->runDirectoryFiles($path, function ($file, $sourcePathInfo) use (&$files, $path) {

                // remove path ".../Themes/.../views/"
                $viewName = preg_replace('#^.*/Themes/.*/views/(.*?$)#', '$1', $file);
                // replace slashes with dots
                $viewName = str_replace('/', '.', $viewName);
                // remove trailing .blade.php
                $viewName = Str::replaceEnd('.blade.php', '', $viewName);
                // now we have the name "xxx.yyy.zzz" or something like that
                $files[$viewName] = $viewName;

            }, $directoryDeep, $regexWhitelist, $regexBlacklist, $addDelimiters);

        }

        return $files;
    }

    /**
     * Overwritten.
     *
     * @param  string  $itemIdentifier
     * @param  bool    $validate
     *
     * @return array
     */
    public function getVendorInfo(string $itemIdentifier, bool $validate = false): array
    {
        $result = parent::getVendorInfo($itemIdentifier, $validate);
        $result['theme_snake_name_folder'] = str_replace('-', '_', data_get($result, 'theme_snake_name'));

        return $result;
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

        $themeLongName = $item;

        if (!is_array($item)) {
            $vendorInfo = $this->getVendorInfo($themeLongName, true);
            $themeToFind = data_get($vendorInfo, 'theme_snake_name_folder');
            if (!($item = $this->getItemsCollection()->where('name', $themeToFind)->first())) {
                Log::warning(sprintf("%s '%s' currently not installed.", $this->addonType, $themeToFind), [__METHOD__]);
                $item = null;
                $result['is_installed'] = false;
            }
        }

        if ($item) { // is theme array object?
            $result['is_enabled'] = true;
            $result['name'] = data_get($item, 'name');
            $result['studly_name'] = $this->getStudlyName($result['name']);
            $result['snake_name'] = $this->getSnakeName($result['name']);
            $themeLongName = $result['snake_name']; // inclusive vendor if composer found
            $result['path'] = $this->getPath('', $result['name']);
            $result['priority'] = 0;

            // read composer.json
            $composerPath = $result['path'].'/composer.json';
            if (file_exists($composerPath)) {
                $result['composer_json'] = file_get_contents($composerPath);
                $result['composer_json'] = json_decode($result['composer_json'], true);
                if ($result['composer_json']) {
                    $themeLongName = data_get($result['composer_json'], 'name');
                }
            }
            $vendorInfo = $this->getVendorInfo($themeLongName);

            // read theme.json
            $themeJsonPath = $result['path'].'/theme.json';
            if (file_exists($themeJsonPath)) {
                $result['theme_json'] = file_get_contents($themeJsonPath);
                $result['theme_json'] = json_decode($result['theme_json'], true);
            }

        } else { // is named
            $vendorInfo = $this->getVendorInfo($themeLongName, true);
            $result['snake_name'] = $vendorInfo['theme_snake_name_folder'];
            $result['name'] = $result['snake_name'];
            $result['studly_name'] = Str::studly($result['name']);
            $result['path'] = $this->getPath('', $result['name']);
        }

        return array_merge($result, $vendorInfo);
    }

    /**
     * Get a collection of all themes.
     *
     * @return Collection
     */
    public function getItemsCollection(): Collection
    {
        return collect($this->getAllThemes());
    }

    /**
     * Get info of all found themes.
     *
     * @param  bool  $enabledOnly
     *
     * @return Collection
     */
    public function getItemInfoList(bool $enabledOnly = true): Collection
    {
        $itemList = collect();

        // priority is not a property, so sort() is a callable
        $collection = $this->getItemsCollection();

        // read theme info of all themes
        /** @var array $theme */
        foreach ($collection as $theme) {
            $itemList->add($this->getItemInfo($theme));
        }

        return $itemList;
    }

    /**
     * @return false|array
     */
    public function getAllThemes(): false|array
    {
        $result = [];

        if ($path = config('theme.theme_path')) {
            $themeNameList = array_filter(glob($path.'/*'), 'is_dir');
            foreach ($themeNameList as $themeName) {
                $themeName = app('system_base_file')->subPath($themeName, $path, true);
                if ($p = $this->getPath('theme.json', $themeName)) {
                    $result[] = json_decode(file_get_contents($p), true);
                }
            }
        }

        return $result;
    }

}