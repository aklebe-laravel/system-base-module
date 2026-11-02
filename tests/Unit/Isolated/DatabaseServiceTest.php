<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Modules\SystemBase\app\Services\DatabaseService;

/**
 * Database switching of the default connection. Uses sqlite files, nothing is queried.
 */
class DatabaseServiceTest extends IsolatedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setConfig('database.default', 'sqlite');
        $this->setConfig('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => 'first.sqlite',
            'prefix'   => '',
        ]);
        $this->container->instance('db', new DatabaseManager($this->container, new ConnectionFactory($this->container)));
    }

    public function test_setDatabaseName_switches_the_default_connection(): void
    {
        $service = new DatabaseService();
        $this->assertSame('first.sqlite', $service->getDatabaseName());

        $service->setDatabaseName('second.sqlite');

        $this->assertSame('second.sqlite', $service->getDatabaseName());
        $this->assertSame('second.sqlite', config('database.connections.sqlite.database'));
    }

    public function test_remember_and_reset_work_like_a_stack(): void
    {
        $service = new DatabaseService();

        $service->rememberCurrentDatabase();
        $service->setDatabaseName('second.sqlite');
        $service->rememberCurrentDatabase();
        $service->setDatabaseName('third.sqlite');

        $service->resetDatabase();
        $this->assertSame('second.sqlite', $service->getDatabaseName());

        $service->resetDatabase();
        $this->assertSame('first.sqlite', $service->getDatabaseName());

        // nothing remembered anymore: stays
        $service->resetDatabase();
        $this->assertSame('first.sqlite', $service->getDatabaseName());
    }
}
