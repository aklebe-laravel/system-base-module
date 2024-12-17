<?php

namespace Modules\SystemBase\app\Services;


use DirectoryIterator;
use Illuminate\Support\Str;
use Modules\SystemBase\app\Services\Base\BaseService;

class FileService extends BaseService
{
    /**
     * @param $unsafeFilename
     *
     * @return array|string
     */
    public function sanitize($unsafeFilename): array|string
    {
        // our list of "unsafe characters", add/remove characters if necessary
        $dangerousCharacters = [" ", '"', "'", "&", "/", "\\", "?", "#"];

        // every forbidden character is replaced by an underscore
        $safeFilename = str_replace($dangerousCharacters, '_', $unsafeFilename);
        $safeFilename = strtolower($safeFilename);

        return $safeFilename;
    }

    /**
     * Delete folder/files recursive
     *
     * @param  string  $directory
     * @param  int     $timespanInSeconds
     * @param  string  $fileFilter
     * @param  false   $deleteEmptyFolders
     * @param  array   $blackListRegEx
     */
    public function deleteFilesOlderThan(string $directory, int $timespanInSeconds = 0, string $fileFilter = '*', bool $deleteEmptyFolders = false, array $blackListRegEx = []): void {
        if ($directory) {
            $now = time();

            $filesFoundTotal = 0;
            $filesDeletedTotal = 0;

            $files = glob($directory.DIRECTORY_SEPARATOR.$fileFilter);
            foreach ($files as $file) {

                if ($blackListRegEx) {
                    foreach ($blackListRegEx as $regEx) {
                        if (preg_match($regEx, $file)) {
                            continue 2;
                        }
                    }
                }

                if (is_file($file)) {
                    $filesFoundTotal++;
                    if ($now - filemtime($file) >= $timespanInSeconds) {
                        @unlink($file);
                        $filesDeletedTotal++;
                    }
                } elseif (is_dir($file)) {
                    $this->deleteFilesOlderThan($file, $timespanInSeconds, $fileFilter, $deleteEmptyFolders);
                }
            }

            if ($deleteEmptyFolders) {
                // another check after files were deleted...
                // check containing files
                $files = glob($directory.DIRECTORY_SEPARATOR."*");
                if (!$files) // no files in folder found
                {
                    @rmdir($directory);
                }
            }
        }
    }

    /**
     * Removing invalid chars for a valid file.
     *
     * @param  string  $partOfPath
     * @param  string  $replacementString
     *
     * @return string
     */
    public function getValidPath(string $partOfPath, string $replacementString = ''): string
    {
        $partOfPath = preg_replace("#[~\#%&*{}:<>?|\"']+#", $replacementString, $partOfPath);
        $partOfPath = preg_replace("#\\+#", '/', $partOfPath);
        $partOfPath = preg_replace("#/+#", '/', $partOfPath); // multiple = 1

        return $partOfPath;
    }

    /**
     * @param  string    $sourcePath
     * @param  callable  $callbackFile   called like callbackFile(string $file, array $sourcePathInfo) : void
     * @param  int       $directoryDeep  -1: infinity, 0: root directory only, 1: 1 directory deeper but not 2 or more
     * @param  array     $regexWhitelist
     * @param  array     $regexBlacklist
     * @param  string    $addDelimiters
     *
     * @return void
     */
    public function runDirectoryFiles(string $sourcePath, callable $callbackFile, int $directoryDeep = -1, array $regexWhitelist = [], array $regexBlacklist = [], string $addDelimiters = ''): void
    {
        if ($sourcePath) {

            if (!is_dir($sourcePath)) {
                $this->error("Missing directory: ".$sourcePath, [__METHOD__]);
            }

            // glob ignores hidden ('.gitkeep') by default
            //            $files = glob($sourcePath.DIRECTORY_SEPARATOR.'*');
            //            foreach ($files as $file) {
            foreach (new DirectoryIterator($sourcePath) as $fileInfo) {
                if ($fileInfo->isDot()) {
                    continue;
                }
                $file = $fileInfo->getPathname();

                // check whitelist
                if ($regexWhitelist) {
                    if (!app('system_base')->isInRegexList($file, $regexWhitelist, $addDelimiters)) {
                        continue;
                    }
                }

                // check blacklist
                if ($regexBlacklist) {
                    if (app('system_base')->isInRegexList($file, $regexBlacklist, $addDelimiters)) {
                        continue;
                    }
                }

                if (is_file($file)) {

                    $sourcePathInfo = pathinfo($file);

                    $callbackFile($file, $sourcePathInfo);

                } elseif (is_dir($file)) {
                    if (($directoryDeep > 0) || ($directoryDeep === -1)) {
                        $this->runDirectoryFiles($file, $callbackFile,
                            ($directoryDeep === -1) ? $directoryDeep : $directoryDeep--, $regexWhitelist,
                            $regexBlacklist, $addDelimiters);
                    }
                }
            }

        }
    }

    /**
     * Subtract a path
     *
     * @param  string  $source
     * @param  string  $subtractPart
     * @param  bool    $cleanSlash
     *
     * @return string|null
     */
    public function subPath(string $source, string $subtractPart, bool $cleanSlash = false): ?string
    {
        $destLen = strlen($subtractPart);
        $subSource = substr($source, 0, $destLen);
        if ($subSource !== $subtractPart) {
            return null;
        }

        $result = substr($source, $destLen);

        if ($cleanSlash) {
            $result = Str::replaceStart('/', '', $result);
        }

        return $result;
    }

    /**
     * wrapper for mkdir(..., 0775, true)
     *
     * @param  string  $path
     *
     * @return bool
     */
    public function createDir(string $path): bool
    {
        return mkdir($path, 0775, true);
    }

    /**
     * @param  string  $source
     * @param  string  $destination
     *
     * @return bool
     */
    public function copyPath(string $source, string $destination): bool
    {
        if (!file_exists($source)) {
            $this->error(sprintf("Source file does not exists: %s", $source));

            return false;
        }

        $destPathInfo = pathinfo($destination);
        if (!is_dir($destPathInfo['dirname'])) {
            $this->createDir($destPathInfo['dirname']);
        }

        if (!copy($source, $destination)) {
            $this->error(sprintf("Copy failed from %s to %s", $source, $destination));

            return false;
        }

        return true;
    }

    /**
     * @param  string  $path
     *
     * @return false|string
     */
    public function loadFile(string $path): false|string
    {
        $path = $this->getValidPath($path);
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        return false;
    }

    /**
     * Can return path like '../../../my-path/current/temp1'
     *
     * @param  string  $fullSourcePath
     * @param  string  $rootPath
     * @param  string  $includeSource
     *
     * @return string|null
     */
    public function makeRelativePath(string $fullSourcePath, string $rootPath, string $includeSource = ''): ?string
    {
        $relativeResult = $this->subPath($fullSourcePath, $rootPath, true);
        if ($includeSource) {
            $relativeIncludeSource = $this->subPath($includeSource, $rootPath, true);
            $relativeIncludeSourceDirs = explode('/', $relativeIncludeSource);
            foreach ($relativeIncludeSourceDirs as $dir) {
                $relativeResult = '../'.$relativeResult;
            }
        }

        return $relativeResult;
    }

}