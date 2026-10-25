<?php

namespace Modules\SystemBase\app\Services;

use AlizHarb\Themer\Facades\Theme;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\AddonObjectService;

/**
 * Theme helpers based on alizharb/laravel-themer.
 */
class ThemeService extends AddonObjectService
{
    /**
     * @var string
     */
    protected string $addonType = 'theme';

    /**
     * Themes have no status, always true.
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
     * Path inside a theme like ".../themes/my-theme/{path}".
     *
     * @param  string  $path          path inside the theme
     * @param  string  $itemName      theme name, default: the active theme
     * @param  string  $generatorKey  not used for themes
     * @param  string  $rootPath      if set use this as project root (to deploy to external projects)
     *
     * @return string
     */
    public static function getPath(string $path = '', string $itemName = '', string $generatorKey = '', string $rootPath = ''): string
    {
        if (!$itemName) {
            $itemName = self::getCurrentTheme();
        }

        if ($rootPath) {
            $result = $rootPath.DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.$itemName;
        } else {
            $result = config('themer.themes_path').DIRECTORY_SEPARATOR.$itemName;
        }

        if ($path) {
            $result .= DIRECTORY_SEPARATOR.$path;
        }

        return $result;
    }

    /**
     * Slug of the parent theme, '' if there is none.
     *
     * @param  string  $themeName
     *
     * @return string
     */
    public function getParentThemeName(string $themeName = ''): string
    {
        return Theme::find($themeName)?->parent?->slug ?? '';
    }

    /**
     * Slug of the active theme.
     *
     * @return string
     */
    public static function getCurrentTheme(): string
    {
        return Theme::getActiveTheme()->slug;
    }

    /**
     * View names of all files in a theme folder, inclusive the parent themes (child themes win).
     *
     * @param  string  $path
     * @param  string  $themeName  default: the active theme
     * @param  int     $directoryDeep
     * @param  array   $regexWhitelist
     * @param  array   $regexBlacklist
     * @param  string  $addDelimiters
     *
     * @return array  view name => view name
     */
    public function getFilesFromTheme(string $path = '', string $themeName = '', int $directoryDeep = 0, array $regexWhitelist = [], array $regexBlacklist = [], string $addDelimiters = ''): array
    {
        if (!$themeName) {
            $themeName = $this->getCurrentTheme();
        }

        $result = [];

        if ($parentTheme = $this->getParentThemeName($themeName)) {
            $result = $this->getFilesFromTheme($path, $parentTheme, $directoryDeep, $regexWhitelist, $regexBlacklist, $addDelimiters);
        }

        return array_merge($result, $this->getFilesInFolder($this->getPath($path, $themeName), $directoryDeep, $regexWhitelist, $regexBlacklist, $addDelimiters));
    }

    /**
     * View names like "xxx.yyy.zzz" of all files in a folder below ".../themes/{theme}/views/".
     *
     * @param  string  $path
     * @param  int     $directoryDeep
     * @param  array   $regexWhitelist
     * @param  array   $regexBlacklist
     * @param  string  $addDelimiters
     *
     * @return array  view name => view name
     */
    public function getFilesInFolder(string $path, int $directoryDeep = 0, array $regexWhitelist = [], array $regexBlacklist = [], string $addDelimiters = ''): array
    {
        $files = [];
        if (is_dir($path)) {

            app('system_base_file')->runDirectoryFiles($path, function ($file, $sourcePathInfo) use (&$files) {

                // remove path ".../themes/.../views/"
                $viewName = preg_replace('#^.*/themes/.*/views/(.*?$)#', '$1', $file);
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
     * Like parent, plus 'theme_snake_name_folder' (same as 'theme_snake_name').
     *
     * @param  string  $itemIdentifier
     * @param  bool    $validate
     *
     * @return array
     */
    public function getVendorInfo(string $itemIdentifier, bool $validate = false): array
    {
        $result = parent::getVendorInfo($itemIdentifier, $validate);
        $result['theme_snake_name_folder'] = data_get($result, 'theme_snake_name');

        return $result;
    }

    /**
     * Get theme info and returns prepared data.
     * If the theme exists, composer.json and theme.json are read and combined with the data.
     * Otherwise, the data is calculated from the name (to prepare/create/clone it).
     *
     * @param  string|array  $item                 theme name or theme.json data
     * @param  string        $destinationRootPath  if given, no local checks will be made, just deploy into this project root path
     *
     * @return array
     */
    public function getItemInfo(mixed $item, string $destinationRootPath = ''): array
    {
        $result = parent::getItemInfo($item, $destinationRootPath);

        $themeLongName = $item;

        if (!$destinationRootPath && !is_array($item)) {
            $vendorInfo = $this->getVendorInfo($themeLongName, true);
            $themeToFind = data_get($vendorInfo, 'theme_snake_name_folder');
            if (!($item = $this->getItemsCollection()
                ->where('name', $themeToFind)
                ->first())
            ) {
                Log::warning(sprintf("%s '%s' currently not installed.", $this->addonType, $themeToFind), [__METHOD__]);
                $item = null;
                $result['is_installed'] = false;
            }
        }

        if (!$destinationRootPath && $item) { // is theme array object?
            $result['is_enabled'] = true;
            $result['name'] = data_get($item, 'name');
            $result['studly_name'] = $this->getStudlyName($result['name']);
            $result['snake_name'] = $this->getSnakeName($result['name']);
            $themeLongName = $result['snake_name']; // inclusive vendor if composer found
            $result['path'] = $this->getPath('', $result['name'], rootPath: $result['path']);
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
            $result['path'] = $this->getPath('', $result['name'], rootPath: $result['path']);
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

        /** @var array $theme */
        foreach ($this->getItemsCollection() as $theme) {
            $itemList->add($this->getItemInfo($theme));
        }

        return $itemList;
    }

    /**
     * Content of theme.json of all theme folders (folders without theme.json are skipped).
     *
     * @return false|array
     */
    public function getAllThemes(): false|array
    {
        $result = [];

        // @todo: Theme::all();

        if ($path = config('themer.themes_path')) {
            foreach (glob($path.'/*', GLOB_ONLYDIR) ?: [] as $themePath) {
                $themeName = app('system_base_file')->subPath($themePath, $path, true);
                $themeJsonPath = $this->getPath('theme.json', $themeName);
                if (is_file($themeJsonPath) && ($themeJson = json_decode(file_get_contents($themeJsonPath), true))) {
                    $result[] = $themeJson;
                }
            }
        }

        return $result;
    }

}