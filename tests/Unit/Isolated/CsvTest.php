<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\Csv;

class CsvTest extends IsolatedTestCase
{
    public function test_addRow_fills_and_orders_columns(): void
    {
        $csv = new Csv();
        $csv->init($this->makeTempDir(), 'x.csv');
        $csv->header = ['id', 'name', 'note'];

        $csv->addRow(['name' => 'Bob', 'unknown' => 'x', 'id' => 1, 'note' => "line1\nline2\tTab"]);

        $this->assertSame([['id' => '1', 'name' => 'Bob', 'note' => 'line1\nline2\tTab']], $csv->rows);
        $this->assertSame(2, $csv->currentRowNumber);
        $this->assertSame(1, $csv->getRowCount());
        $this->assertSame('Bob', $csv->getLastRow()['name']);
    }

    public function test_save_and_load_roundtrip(): void
    {
        $dir = $this->makeTempDir().'/sub';
        $csv = new Csv();
        $csv->init($dir, 'data.csv');
        $csv->header = ['id', 'name'];
        $csv->addRow(['id' => 1, 'name' => 'Müller; "quoted"']);
        $csv->addRow(['id' => 2, 'name' => 'Ä']);
        $this->assertTrue($csv->save());

        $loaded = new Csv();
        $loaded->init($dir, 'data.csv');
        $seen = [];
        $this->assertTrue($loaded->load(function (array $row) use (&$seen) {
            $seen[] = $row['id'];

            return true;
        }));

        $this->assertSame(['id', 'name'], $loaded->header);
        $this->assertSame(['1', '2'], $seen);
        $this->assertSame('Müller; "quoted"', $loaded->rows[0]['name']);
        $this->assertSame(['id' => '2', 'name' => 'Ä'], $loaded->findRowByColumn('id', 2));
        $this->assertTrue($loaded->hasHeader('name'));
        $this->assertFalse($loaded->hasHeader('missing'));
    }

    public function test_load_stops_when_callback_returns_false(): void
    {
        $dir = $this->makeTempDir();
        $this->makeFile($dir.'/a.csv', "id;name\n1;a\n2;b\n");

        $csv = new Csv();
        $csv->init($dir, 'a.csv');
        $this->assertFalse($csv->load(fn(array $row) => $row['id'] !== '2'));
        $this->assertCount(1, $csv->rows);
    }

    public function test_load_ignores_values_without_header_column(): void
    {
        $dir = $this->makeTempDir();
        $this->makeFile($dir.'/a.csv', "id;name\n1;a;extra\n");

        $csv = new Csv();
        $csv->init($dir, 'a.csv');
        $this->assertTrue($csv->load());
        $this->assertSame([['id' => '1', 'name' => 'a']], $csv->rows);
    }

    public function test_load_missing_file_or_filename(): void
    {
        $csv = new Csv();
        $this->assertFalse($csv->load());

        $csv->init($this->makeTempDir(), 'missing.csv');
        $this->assertFalse($csv->load());

        $this->assertNotEmpty($this->getLoggedMessages('error') || $this->getLoggedMessages('warning'));
    }

    public function test_save_split_files_and_delete_parts(): void
    {
        $dir = $this->makeTempDir();
        $csv = new Csv();
        $csv->init($dir, 'big.csv');
        $csv->header = ['id'];
        foreach (range(1, 5) as $id) {
            $csv->addRow(['id' => $id]);
        }
        $csv->setMaxCsvRowsToSave(2);
        $this->assertTrue($csv->save());

        $files = Csv::getSplitCsvFiles($csv->getFullFilePath());
        sort($files);
        $this->assertSame([$dir.'/big.csv', $dir.'/big_02.csv', $dir.'/big_03.csv'], $files);
        $this->assertSame("id\n5\n", file_get_contents($dir.'/big_03.csv'));

        Csv::deleteSplitCsvFiles($csv->getFullFilePath());
        $this->assertSame([], Csv::getSplitCsvFiles($csv->getFullFilePath()));
    }

    public function test_save_append_writes_header_once(): void
    {
        $dir = $this->makeTempDir();
        $csv = new Csv();
        $csv->init($dir, 'log.csv');
        $csv->header = ['id'];
        $csv->addRow(['id' => 1]);
        $this->assertTrue($csv->save(false, true));
        $this->assertTrue($csv->save(false, true));

        $this->assertSame("id\n1\n1\n", file_get_contents($dir.'/log.csv'));
    }

    public function test_save_without_header_fails(): void
    {
        $csv = new Csv();
        $csv->init($this->makeTempDir(), 'x.csv');
        $this->assertFalse($csv->save());
    }

    public function test_deleteAllRowsByColumn(): void
    {
        $csv = new Csv();
        $csv->header = ['id', 'type'];
        $csv->addRow(['id' => 1, 'type' => 'a']);
        $csv->addRow(['id' => 2, 'type' => 'b']);
        $csv->addRow(['id' => 3, 'type' => 'a']);

        $csv->deleteAllRowsByColumn('type', 'a');

        $this->assertSame([1 => ['id' => '2', 'type' => 'b']], $csv->rows);
        $this->assertSame('2', $csv->getLastRow()['id']);
    }
}
