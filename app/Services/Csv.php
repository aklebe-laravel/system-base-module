<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Support\Facades\Log;

class Csv
{
    /**
     * Zero based header index. Values are header names.
     *
     * @var array
     */
    public array $header = [];

    /**
     * @var array
     */
    public array $rows = [];

    /**
     * If true fill up new rows with missing columns declared in header and set it to ''.
     * Also, columns will reordered like given header.
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
    public ?string $enclosure = null;

    /**
     * @var string
     */
    public string $filename = "";

    /**
     * @var string
     */
    public string $folder = "";

    /**
     * @var int
     */
    protected int $currentRowNumber = 1;

    /**
     * @var int
     */
    protected int $splitFilesRowLargerThan = 0;

    /**
     * @param  string  $folder without trailing slash
     * @param  string|null  $filename
     * @param  string  $delimiter
     * @param  string  $enclosure
     */
    public function init(string $folder, ?string $filename = null, string $delimiter = ";", string $enclosure = '"'): void
    {
        if ($filename === null)
        {
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
     * @return array
     */
    public function createRow(): array
    {
        $row = array();
        foreach ($this->header as $csvColumnName)
        {
            $row[$csvColumnName] = '';
        }
        return $row;
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
        if (!$this->rows)
            return null;
        return $this->rows[count($this->rows) - 1];
    }

    /**
     * @param $handle
     * @return array|null
     */
    public function loadNextRow($handle): ?array
    {
        if ($this->header)
        {
            $row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure);
            $result = array();
            if ($row)
            {
                foreach ($row as $index => $column)
                {
                    $result[$this->header[$index]] = $column;
                }
            }
            return $result;
        }
        Log::error('No header');
        return null;
    }

    /**
     * @param $row
     * @return void
     */
    public function addRow($row): void
    {
        if ($this->autoAdjustDataRows)
        {
            // add missing columns
            foreach ($this->header as $columnName)
            {
                if (!isset($row[$columnName]))
                    $row[$columnName] = '';
            }

            // reorder row
            $newRow = $this->createRow();
            foreach ($row as $columnName => $columnValue)
            {
                if (isset($newRow[$columnName])) // only use columns are present
                {
                    // replace line breaks
                    $columnValue = preg_replace( "/\r\n?|\n/", '\\n', $columnValue);
                    $columnValue = preg_replace( "/\t/", '\\t', $columnValue);

                    // assign value
                    $newRow[$columnName] = $columnValue;
                }
            }
            $row = $newRow;
        }

        $this->rows[] = $row;
        $this->currentRowNumber++;
    }

    /**
     * @param $handle
     * @return array|false
     */
    public function loadHeader($handle): false|array
    {
        $this->header = fgetcsv($handle, 0, $this->delimiter, $this->enclosure);
        return $this->header;
    }

    /**
     * @param  callable|null  $callbackPerRow
     * @param  callable|null  $callbackLowPerformance
     * @return bool
     */
    public function load(callable $callbackPerRow = null, callable $callbackLowPerformance = null): bool
    {
        if (!$this->filename)
        {
            Log::error('Filename not set, call init before load a file.');
            return false;
        }

        // bugfix mac line breaks
        ini_set("auto_detect_line_endings", true);

        $filename = $this->getFullFilePath();
        try
        {
            // open file
            if (($handle = fopen($filename, "r")) !== false)
            {
                // load header
                $this->loadHeader($handle);

                // load all rows
                while ($row = $this->loadNextRow($handle))
                {
                    if ($callbackPerRow !== null)
                    {
                        try
                        {
                            // callback
                            $callbackPerRow($row);
                        }
                        catch(\Exception $ex)
                        {
                            Log::error($ex->getMessage());
                        }
                    }
                    $this->addRow($row);
                }

                // close file
                fclose($handle);
                return true;
            }
            else
            {
                Log::error('Failed to open file: ' . $filename);
            }
        }
        catch (\Exception $e)
        {
            Log::error('Exception: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * @param $row
     * @return void
     */
    public function encodeRow(&$row): void
    {
        if ($row)
        {
            foreach ($row as &$col)
            {
                $col = Encoding::toUTF8($col);
            }
        }
    }

    /**
     * @param $handle
     * @param $row
     * @return void
     */
    public function addRowToFile($handle, &$row): void
    {
        $this->encodeRow($row);
        fputcsv($handle, $row, $this->delimiter, $this->enclosure);
    }

    /**
     * @param  int  $maxRows
     * @return void
     */
    public function setMaxCsvRowsToSave(int $maxRows = 0): void
    {
        $this->splitFilesRowLargerThan = $maxRows;
    }

    /**
     * @param  string  $filename
     * @param  int  $numberOfFile
     * @return string
     */
    protected function _getSplitCsvFilename(string $filename, int $numberOfFile = 2): string
    {
        $fileParts = explode('.', $filename);
        $partPrefix = sprintf("_%02d", $numberOfFile);
        if (count($fileParts) > 1)
        {
            $fileParts[count($fileParts) - 2] .= $partPrefix;
            $filename = implode('.', $fileParts);
        }
        else
        {
            $filename.= $partPrefix;
        }
        return $filename;
    }

    /**
     * @param $filename
     * @return array
     */
    public static function getSplitCsvFiles($filename): array
    {
        if ($filename)
        {
            $fileParts = explode('.', $filename);
            if (count($fileParts) > 1)
            {
                $fileParts[count($fileParts) - 2] .= '*';
                $filename = implode('.', $fileParts);
            }
            else
            {
                $filename .= '*';
            }

            $result = glob($filename);
            return ($result) ? $result : array();
        }
        return array();
    }

    /**
     * @param $filename
     * @return void
     */
    public static function deleteSplitCsvFiles($filename): void
    {
        if ($filename)
        {
            foreach (self::getSplitCsvFiles($filename) as $f)
            {
                if (!@unlink($f))
                {
                    Log::error(__METHOD__ . ' failed to delete CSV: ' . $f);
                }
            }
        }
    }

    /**
     * @param  bool  $deleteAllFileParts
     * @param  bool  $appendFile
     * @return bool
     */
    public function save(bool $deleteAllFileParts = false, bool $appendFile = false): bool
    {
        if (!$this->filename)
        {
            Log::error('Filename not set. Call init before save a file.');
            return false;
        }

        if (!$this->header)
        {
            Log::error('No header found.');
            return false;
        }

        if (!is_dir($this->folder))
        {
            @mkdir($this->folder, 0775, true);
        }

        $filename = $this->getFullFilePath();
        $fileExists = file_exists($filename);
        try
        {
            // delete previously generated csv file parts
            if ($deleteAllFileParts)
            {
                $this->deleteSplitCsvFiles($filename);
            }

            // open file
            if (($handle = fopen($filename, $appendFile ? "a" : "w")) !== false)
            {
                // save header
                if ((!$appendFile) || (!$fileExists)) {
                    $this->addRowToFile($handle, $this->header);
                }

                // save all rows
                $rowIndex = 0;
                $filePartIndex = 1;
                foreach ($this->rows as $row)
                {
                    $this->addRowToFile($handle, $row);

                    // check have to create multiple file parts
                    $rowIndex++;
                    if (($this->splitFilesRowLargerThan > 0) && ($rowIndex >= $this->splitFilesRowLargerThan))
                    {
                        $rowIndex = 0;
                        $filePartIndex++;

                        // close current file
                        fclose($handle);
                        // create the new part
                        if (($handle = fopen($this->_getSplitCsvFilename($filename, $filePartIndex), "w")) !== false)
                        {
                            $this->addRowToFile($handle, $this->header);
                        }
                        else
                        {
                            throw new \Exception("Failed to create next csv file part.");
                        }
                    }
                }

                // close file
                fclose($handle);
                return true;
            }
            else
            {
                Log::error('Failed to open file: ' . $filename);
            }
        }
        catch (\Exception $e)
        {
            Log::error('Exception: ' . $e->getMessage());
        }
        return false;
    }

    /**
     * @param $index
     * @param $value
     * @return mixed|null
     */
    public function findRowByColumn($index, $value): mixed
    {
        foreach ($this->rows as $kRow => $vRow)
        {
            // check valid rows
            if (!isset($vRow[$index]))
                return null;

            if ($vRow[$index] == $value)
                return $vRow;
        }
        return null;
    }

    /**
     * Delete all rows indexed by $index with given value in $value.
     *
     * @param $index
     * @param $value
     */
    public function deleteAllRowsByColumn($index, $value): void
    {
        foreach ($this->rows as $kRow => $vRow)
        {
            // check valid rows
            if (!isset($vRow[$index]))
                return;

            if ($vRow[$index] == $value)
            {
                unset($this->rows[$kRow]);
            }
        }
        return;
    }

    /**
     * @param $headerName
     * @return bool
     */
    public function hasHeader($headerName): bool
    {
        foreach ($this->header as $header)
        {
            if ($header == $headerName)
                return true;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getFullFilePath(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . $this->filename;
    }

}