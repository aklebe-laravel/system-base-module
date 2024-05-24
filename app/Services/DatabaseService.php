<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\SystemBase\app\Services\Base\BaseService;

class DatabaseService extends BaseService
{
    /**
     * @var array
     */
    protected array $rememberDatabases = [];

    /**
     * @return string
     */
    public function getDatabaseName(): string
    {
        return DB::getDatabaseName();
    }

    /**
     * @param  string  $name
     * @return void
     */
    public function setDatabaseName(string $name): void
    {
        // DB::setDatabaseName($name);

        // Fake/set the new db as config setup
        $mysqlConfig = config('database.connections.mysql');
        $mysqlConfig['database'] = $name;
        config(['database.connections.mysql' => $mysqlConfig]);
        DB::purge();
    }

    /**
     * @return void
     */
    public function rememberCurrentDatabase(): void
    {
        $this->rememberDatabases[] = $this->getDatabaseName();
        // $this->debug("Database: ".$this->getDatabaseName());
    }

    /**
     * @return void
     */
    public function resetDatabase(): void
    {
        if ($this->rememberDatabases) {
            $this->debug("rememberDatabases: ", [$this->rememberDatabases]);
            $last = array_pop($this->rememberDatabases);
            $this->setDatabaseName($last);
        }
    }
}