<?php

namespace Modules\SystemBase\app\Services;

use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Simple CSV reader/writer.
 *
 * Usage:
 * - call init() first (folder, filename, delimiter, enclosure)
 * - load(): reads the header and all rows (as header => value arrays)
 * - set $header, addRow() and save() to write a file (optionally split into parts, see setMaxCsvRowsToSave())
 *
 * Values are written as UTF-8, line breaks and tabs inside values are written as literal "\n" and "\t".
 */
class Csv
{
    /**
     * Zero based header index. Values are header names.
     *
     * @var array
     */
    public array $header = [];

    /**
     * Loaded or added rows.
     *
     * @var array
     */
    public array $rows = [];

    /**
     * If true, addRow() fills up missing columns declared in the header with '' and
     * orders the columns like the header. Columns not in the header are dropped.
     *
     * @var bool
     */
    protected bool $autoAdjustDataRows = true;

    /**
     * @var string
     */
    public string $delimiter = ";";

    /**
     * @var string|null
     */
    public ?string $enclosure = '"';

    /**
     * Escape char for fgetcsv()/fputcsv(). Set explicitly because the default is deprecated since PHP 8.4.
     *
     * @var string
     */
    public string $escape = '\\';

    /**
     * @var string
     */
    public string $filename = "";

    /**
     * @var string
     */
    public string $folder = "";

    /**
     * Number of the next row, starting with 1. After load() it's the count of rows + 1.
     *
     * @var int
     */
    public int $currentRowNumber = 1;

    /**
     * See setMaxCsvRowsToSave().
     *
     * @var int
     */
    protected int $splitFilesRowLargerThan = 0;

    /**
     * Resets header and rows and sets the file parameters.
     *
     * @param  string       $folder    without trailing slash
     * @param  string|null  $filename  null: a new unique temp file is created in $folder
     * @param  string       $delimiter
     * @param  string       $enclosure
     *
     * @return void
     */
    public function init(string $folder, ?string $filename = null, string $delimiter = ";", string $enclosure = '"'): void
    {
        if ($filename === null) {
            $filename = basename(tempnam($folder, 'csv_'));
        }

        $this->header = [];
        $this->rows = [];
        $this->folder = $folder;
        $this->filename = $filename;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->currentRowNumber = 1;
    }

    /**
     * Empty row with all header columns set to ''.
     *
     * @return array
     */
    public function createRow(): array
    {
        return array_fill_keys($this->header, '');
    }

    /**
     * @return int
     */
    public function getRowCount(): int
    {
        return count($this->rows);
    }

    /**
     * @return mixed|null
     */
    public function getLastRow(): mixed
    {
        if (!$this->rows) {
            return null;
        }

        return end($this->rows);
    }

    /**
     * Reads the next line as header => value array.
     * Values without a header column are ignored.
     *
     * @param  resource  $handle
     *
     * @return array|null  [] at the end of the file, null if no header was loaded
     */
    public function loadNextRow($handle): ?array
    {
        if (!$this->header) {
            Log::error('No header');

            return null;
        }

        $row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure ?? '"', $this->escape);
        $result = [];
        if ($row) {
            foreach ($row as $index => $column) {
                if (isset($this->header[$index])) {
                    $result[$this->header[$index]] = $column;
                }
            }
        }

        return $result;
    }

    /**
     * Adds a row (see $autoAdjustDataRows).
     *
     * @param  array  $row
     *
     * @return void
     */
    public function addRow($row): void
    {
        if ($this->autoAdjustDataRows) {
            $newRow = $this->createRow();
            foreach ($row as $columnName => $columnValue) {
                // only columns present in the header
                if (array_key_exists($columnName, $newRow)) {
                    // keep line breaks and tabs in one csv line
                    $columnValue = preg_replace("/\r\n?|\n/", '\\n', (string) $columnValue);
                    $columnValue = str_replace("\t", '\\t', $columnValue);

                    $newRow[$columnName] = $columnValue;
                }
            }
            $row = $newRow;
        }

        $this->rows[] = $row;
        $this->currentRowNumber++;
    }

    /**
     * Reads the first line as header.
     *
     * @param  resource  $handle
     *
     * @return array|false
     */
    public function loadHeader($handle): false|array
    {
        $header = fgetcsv($handle, 0, $this->delimiter, $this->enclosure ?? '"', $this->escape);
        $this->header = $header ?: [];

        return $header;
    }

    /**
     * Loads the file set by init().
     * Exceptions thrown by $callbackPerRow are logged and the row is still added.
     *
     * @param  callable|null  $callbackPerRow          called with the row before it is added, return false to stop
     * @param  callable|null  $callbackLowPerformance  not used
     *
     * @return bool  false on errors or if $callbackPerRow stopped the import
     */
    public function load(?callable $callbackPerRow = null, ?callable $callbackLowPerformance = null): bool
    {
        if (!$this->filename) {
            Log::error('Filename not set, call init before load a file.');

            return false;
        }

        $filename = $this->getFullFilePath();
        $handle = false;
        try {
            if (($handle = @fopen($filename, "r")) === false) {
                Log::error('Failed to open file: '.$filename);

                return false;
            }

            $this->loadHeader($handle);

            while ($row = $this->loadNextRow($handle)) {
                if ($callbackPerRow !== null) {
                    try {
                        if (!$callbackPerRow($row)) {
                            return false;
                        }
                    } catch (Exception $ex) {
                        Log::error($ex->getMessage());
                    }
                }
                $this->addRow($row);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Exception: '.$e->getMessage());
        } finally {
            if ($handle) {
                fclose($handle);
            }
        }

        return false;
    }

    /**
     * Converts all values of a row to UTF-8 (in place).
     *
     * @param  array|null  $row
     *
     * @return void
     */
    public function encodeRow(&$row): void
    {
        if ($row) {
            foreach ($row as &$col) {
                $col = Encoding::toUTF8($col);
            }
        }
    }

    /**
     * Writes a row (converted to UTF-8, in place) to the file.
     *
     * @param  resource  $handle
     * @param  array     $row
     *
     * @return void
     */
    public function addRowToFile($handle, &$row): void
    {
        $this->encodeRow($row);
        fputcsv($handle, $row, $this->delimiter, $this->enclosure ?? '"', $this->escape);
    }

    /**
     * Split the file in parts with max $maxRows rows each (plus header) when saving. 0: no split.
     * Parts are named like "file_02.csv", "file_03.csv", ...
     *
     * @param  int  $maxRows
     *
     * @return void
     */
    public function setMaxCsvRowsToSave(int $maxRows = 0): void
    {
        $this->splitFilesRowLargerThan = $maxRows;
    }

    /**
     * Filename of a part like "file_02.csv" for "file.csv".
     *
     * @param  string  $filename
     * @param  int     $numberOfFile
     *
     * @return string
     */
    protected function _getSplitCsvFilename(string $filename, int $numberOfFile = 2): string
    {
        $fileParts = explode('.', $filename);
        $partSuffix = sprintf("_%02d", $numberOfFile);
        if (count($fileParts) > 1) {
            $fileParts[count($fileParts) - 2] .= $partSuffix;

            return implode('.', $fileParts);
        }

        return $filename.$partSuffix;
    }

    /**
     * All files matching "file*.csv" for "file.csv" (the file itself and its parts).
     *
     * @param  string  $filename
     *
     * @return array
     */
    public static function getSplitCsvFiles($filename): array
    {
        if (!$filename) {
            return [];
        }

        $fileParts = explode('.', $filename);
        if (count($fileParts) > 1) {
            $fileParts[count($fileParts) - 2] .= '*';
            $filename = implode('.', $fileParts);
        } else {
            $filename .= '*';
        }

        return glob($filename) ?: [];
    }

    /**
     * Deletes all files of getSplitCsvFiles().
     *
     * @param  string  $filename
     *
     * @return void
     */
    public static function deleteSplitCsvFiles($filename): void
    {
        foreach (self::getSplitCsvFiles($filename) as $f) {
            if (!@unlink($f)) {
                Log::error(__METHOD__.' failed to delete CSV: '.$f);
            }
        }
    }

    /**
     * Writes header and rows to the file set by init(). The folder is created if needed.
     *
     * @param  bool  $deleteAllFileParts  delete the file and all its parts before writing
     * @param  bool  $appendFile          append rows (the header is only written to a new file)
     *
     * @return bool
     */
    public function save(bool $deleteAllFileParts = false, bool $appendFile = false): bool
    {
        if (!$this->filename) {
            Log::error('Filename not set. Call init before save a file.');

            return false;
        }

        if (!$this->header) {
            Log::error('No header found.');

            return false;
        }

        if (!is_dir($this->folder)) {
            @mkdir($this->folder, 0775, true);
        }

        $filename = $this->getFullFilePath();
        $handle = false;
        try {
            // delete previously generated csv file parts
            if ($deleteAllFileParts) {
                self::deleteSplitCsvFiles($filename);
            }

            $fileExists = file_exists($filename);
            if (($handle = @fopen($filename, $appendFile ? "a" : "w")) === false) {
                Log::error('Failed to open file: '.$filename);

                return false;
            }

            if (!$appendFile || !$fileExists) {
                $header = $this->header;
                $this->addRowToFile($handle, $header);
            }

            $rowIndex = 0;
            $filePartIndex = 1;
            foreach ($this->rows as $row) {
                $this->addRowToFile($handle, $row);

                // create the next file part?
                $rowIndex++;
                if (($this->splitFilesRowLargerThan > 0) && ($rowIndex >= $this->splitFilesRowLargerThan)) {
                    $rowIndex = 0;
                    $filePartIndex++;

                    fclose($handle);
                    if (($handle = @fopen($this->_getSplitCsvFilename($filename, $filePartIndex), "w")) === false) {
                        throw new Exception("Failed to create next csv file part.");
                    }
                    $header = $this->header;
                    $this->addRowToFile($handle, $header);
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Exception: '.$e->getMessage());
        } finally {
            if ($handle) {
                fclose($handle);
            }
        }

        return false;
    }

    /**
     * First row where column $index equals $value (loose comparison).
     * Stops at the first row without this column.
     *
     * @param  string|int  $index
     * @param  mixed       $value
     *
     * @return mixed|null
     */
    public function findRowByColumn($index, $value): mixed
    {
        foreach ($this->rows as $vRow) {
            if (!isset($vRow[$index])) {
                return null;
            }

            if ($vRow[$index] == $value) {
                return $vRow;
            }
        }

        return null;
    }

    /**
     * Delete all rows where column $index equals $value (loose comparison).
     * Stops at the first row without this column. Row keys are not re-indexed.
     *
     * @param  string|int  $index
     * @param  mixed       $value
     *
     * @return void
     */
    public function deleteAllRowsByColumn($index, $value): void
    {
        foreach ($this->rows as $kRow => $vRow) {
            if (!isset($vRow[$index])) {
                return;
            }

            if ($vRow[$index] == $value) {
                unset($this->rows[$kRow]);
            }
        }
    }

    /**
     * @param  string  $headerName
     *
     * @return bool
     */
    public function hasHeader($headerName): bool
    {
        return in_array($headerName, $this->header);
    }

    /**
     * @return string
     */
    public function getFullFilePath(): string
    {
        return $this->folder.DIRECTORY_SEPARATOR.$this->filename;
    }

}
