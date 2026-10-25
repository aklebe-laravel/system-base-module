<?php

namespace Modules\SystemBase\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\SystemBase\app\Services\Base\BaseService;

/**
 * Switches the database of the default connection, for example to import into another database.
 * Use rememberCurrentDatabase() before setDatabaseName() and resetDatabase() afterward.
 */
class DatabaseService extends BaseService
{
    /**
     * Stack of database names remembered by rememberCurrentDatabase().
     *
     * @var array
     */
    protected array $rememberDatabases = [];

    /**
     * Database name of the default connection.
     *
     * @return string
     */
    public function getDatabaseName(): string
    {
        return DB::getDatabaseName();
    }

    /**
     * Switches the default connection to another database (runtime config only)
     * and reconnects on the next query.
     *
     * @param  string  $name
     *
     * @return void
     */
    public function setDatabaseName(string $name): void
    {
        $connectionName = config('database.default', 'mysql');
        config(['database.connections.'.$connectionName.'.database' => $name]);
        DB::purge($connectionName);
    }

    /**
     * Pushes the current database name to the stack.
     *
     * @return void
     */
    public function rememberCurrentDatabase(): void
    {
        $this->rememberDatabases[] = $this->getDatabaseName();
    }

    /**
     * Switches back to the last remembered database (if any).
     *
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