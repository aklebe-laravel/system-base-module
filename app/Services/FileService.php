<?php
declare(strict_types = 1);

namespace Modules\SystemBase\app\Services;

use DirectoryIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * File and directory helpers. As singleton: app('system_base_file').
 */
class FileService extends BaseService
{
    /**
     * Makes a safe lower case path/filename:
     * - umlauts are transliterated (Ä -> AE, ß -> SS)
     * - everything except a-z, 0-9 and '/' becomes '_' (also dots, so file extensions are lost!)
     * - multiple '_' become one, '_' around '/' and at the start/end are removed
     *
     * Example: "Äpfel & Birnen/Neue Liste.txt" => "aepfel_birnen/neue_liste_txt"
     *
     * @param  string  $input
     *
     * @return array|string
     */
    public function sanitize($input): array|string
    {
        $path = Str::ascii((string) $input, 'de');
        $path = strtolower($path);
        $path = preg_replace('/[^a-z0-9\/]/', '_', $path);
        $path = preg_replace('/_{2,}/', '_', $path);
        $path = preg_replace('/_?\/_?/', '/', $path);

        return trim($path, '_');
    }

    /**
     * Deletes files older than $timespanInSeconds in $directory and its sub directories.
     *
     * @param  string  $directory
     * @param  int     $timespanInSeconds  0: delete all matching files
     * @param  string  $fileFilter         glob pattern, also used for sub directories
     * @param  bool    $deleteEmptyFolders  removes $directory (and sub directories) if empty afterward
     * @param  array   $blackListRegEx      full regex patterns (with delimiters) of paths to keep
     *
     * @return void
     */
    public function deleteFilesOlderThan(string $directory, int $timespanInSeconds = 0, string $fileFilter = '*', bool $deleteEmptyFolders = false, array $blackListRegEx = []): void
    {
        if (!$directory) {
            return;
        }

        $now = time();
        foreach (glob($directory.DIRECTORY_SEPARATOR.$fileFilter) ?: [] as $file) {

            foreach ($blackListRegEx as $regEx) {
                if (preg_match($regEx, $file)) {
                    continue 2;
                }
            }

            if (is_file($file)) {
                if ($now - filemtime($file) >= $timespanInSeconds) {
                    @unlink($file);
                }
            } elseif (is_dir($file)) {
                $this->deleteFilesOlderThan($file, $timespanInSeconds, $fileFilter, $deleteEmptyFolders, $blackListRegEx);
            }
        }

        // check again after files were deleted (glob() ignores hidden files)
        if ($deleteEmptyFolders && !glob($directory.DIRECTORY_SEPARATOR.'*')) {
            @rmdir($directory);
        }
    }

    /**
     * Removes invalid chars for a valid file path:
     * - chars ~ # % & * { } : < > ? | " ' are replaced by $replacementString
     * - backslashes become '/'
     * - multiple '/' become one
     *
     * @param  string  $partOfPath
     * @param  string  $replacementString
     *
     * @return string
     */
    public function getValidPath(string $partOfPath, string $replacementString = ''): string
    {
        $partOfPath = preg_replace('#[~\#%&*{}:<>?|"\']+#', $replacementString, $partOfPath);
        $partOfPath = preg_replace('#\\\\+#', '/', $partOfPath);

        return preg_replace('#/+#', '/', $partOfPath);
    }

    /**
     * Calls $callbackFile for every file in $sourcePath (and sub directories depending on $directoryDeep).
     * Hidden files like '.gitkeep' are included. The order is not sorted.
     * The white/blacklist is checked against the full path of files and directories.
     *
     * @param  string    $sourcePath
     * @param  callable  $callbackFile   called like callbackFile(string $file, array $sourcePathInfo): void
     *                                   $sourcePathInfo is the result of pathinfo($file)
     * @param  int       $directoryDeep  -1: infinity, 0: root directory only, 1: 1 directory deeper but not 2 or more
     * @param  array     $regexWhitelist
     * @param  array     $regexBlacklist
     * @param  string    $addDelimiters  if set (like '#'), every pattern is wrapped by it
     *
     * @return void
     */
    public function runDirectoryFiles(string $sourcePath, callable $callbackFile, int $directoryDeep = -1, array $regexWhitelist = [], array $regexBlacklist = [], string $addDelimiters = ''): void
    {
        if (!$sourcePath || !is_dir($sourcePath)) {
            return;
        }

        /** @var SystemService $systemService */
        $systemService = app('system_base');

        foreach (new DirectoryIterator($sourcePath) as $fileInfo) {
            if ($fileInfo->isDot()) {
                continue;
            }
            $file = $fileInfo->getPathname();

            if ($regexWhitelist && !$systemService->isInRegexList($file, $regexWhitelist, $addDelimiters)) {
                continue;
            }

            if ($regexBlacklist && $systemService->isInRegexList($file, $regexBlacklist, $addDelimiters)) {
                continue;
            }

            if (is_file($file)) {
                $callbackFile($file, pathinfo($file));
            } elseif (is_dir($file) && ($directoryDeep !== 0)) {
                // -1 stays -1 (infinity), otherwise one level less for the sub directory
                $this->runDirectoryFiles($file,
                    $callbackFile,
                    ($directoryDeep === -1) ? -1 : $directoryDeep - 1,
                    $regexWhitelist,
                    $regexBlacklist,
                    $addDelimiters);
            }
        }
    }

    /**
     * Removes $subtractPart from the start of $source.
     * Example: subPath('/var/www/app/x.php', '/var/www', true) => 'app/x.php'
     *
     * @param  string  $source
     * @param  string  $subtractPart
     * @param  bool    $cleanSlash  removes a leading '/' from the result
     *
     * @return string|null  null if $source does not start with $subtractPart
     */
    public function subPath(string $source, string $subtractPart, bool $cleanSlash = false): ?string
    {
        if (!str_starts_with($source, $subtractPart)) {
            return null;
        }

        $result = substr($source, strlen($subtractPart));

        if ($cleanSlash) {
            $result = Str::replaceStart('/', '', $result);
        }

        return $result;
    }

    /**
     * Creates a directory recursively with 0775 (if it does not exist yet).
     *
     * @param  string  $path
     *
     * @return bool
     */
    public function createDir(string $path): bool
    {
        return is_dir($path) || mkdir($path, 0775, true);
    }

    /**
     * Deletes the content of a directory, but keeps the directory.
     *
     * @param  string  $path
     *
     * @return bool
     */
    public function cleanDir(string $path): bool
    {
        return File::cleanDirectory($path);
    }

    /**
     * Deletes a directory recursively.
     *
     * @param  string  $path
     *
     * @return bool
     */
    public function deleteDir(string $path): bool
    {
        return File::deleteDirectory($path);
    }

    /**
     * Copies a file and creates the destination directory if needed. Errors are logged.
     *
     * @param  string  $source
     * @param  string  $destination
     *
     * @return bool
     */
    public function copyPath(string $source, string $destination): bool
    {
        if (!file_exists($source)) {
            $this->error(sprintf("Source file does not exist: %s", $source));

            return false;
        }

        $this->createDir(dirname($destination));

        if (!copy($source, $destination)) {
            $this->error(sprintf("Copy failed from %s to %s", $source, $destination));

            return false;
        }

        return true;
    }

    /**
     * Content of a file (the path is cleaned by getValidPath() first), false if it does not exist.
     *
     * @param  string  $path
     *
     * @return false|string
     */
    public function loadFile(string $path): false|string
    {
        $path = $this->getValidPath($path);
        if (is_file($path)) {
            return file_get_contents($path);
        }

        return false;
    }

    /**
     * Path of $fullSourcePath relative to $rootPath.
     * If $includeSource (a path below $rootPath too) is given, one '../' per path part
     * of $includeSource is prepended. Example:
     * makeRelativePath('/root/my-path/temp1', '/root', '/root/a/b/c') => '../../../my-path/temp1'
     *
     * @param  string  $fullSourcePath
     * @param  string  $rootPath
     * @param  string  $includeSource
     *
     * @return string|null  null if $fullSourcePath is not inside $rootPath
     */
    public function makeRelativePath(string $fullSourcePath, string $rootPath, string $includeSource = ''): ?string
    {
        $relativeResult = $this->subPath($fullSourcePath, $rootPath, true);
        if (($relativeResult !== null) && $includeSource) {
            $relativeIncludeSource = (string) $this->subPath($includeSource, $rootPath, true);
            $relativeResult = str_repeat('../', count(explode('/', $relativeIncludeSource))).$relativeResult;
        }

        return $relativeResult;
    }

}