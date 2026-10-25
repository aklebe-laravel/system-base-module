<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Modules\SystemBase\app\Services\ModelService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Checks the generated SQL and bindings only, so no PDO driver and no database are needed.
 * The connection gets a PDO closure that is never called (it would throw).
 */
class ModelServiceTest extends IsolatedTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = new MySqlConnection(function () {
            throw new RuntimeException('No database access expected in this unit test.');
        });

        $resolver = new ConnectionResolver(['default' => $connection]);
        $resolver->setDefaultConnection('default');
        Model::setConnectionResolver($resolver);
    }

    protected function tearDown(): void
    {
        Model::unsetConnectionResolver();

        parent::tearDown();
    }

    /**
     * @param  Builder  $builder
     *
     * @return array{0: string, 1: array}  sql and bindings
     */
    protected function sqlOf(Builder $builder): array
    {
        return [$builder->toSql(), $builder->getBindings()];
    }

    public static function idParamProvider(): array
    {
        $prefix = 'select * from `items` where ';

        return [
            'single'      => ['5', $prefix.'(`id` = ?)', ['5']],
            'list'        => ['1,2,3', $prefix.'(`id` = ? or `id` = ? or `id` = ?)', ['1', '2', '3']],
            'range'       => ['7-9', $prefix.'(`id` between ? and ?)', ['7', '9']],
            'mixed'       => ['1, 3-4 ,18', $prefix.'(`id` = ? or `id` between ? and ? or `id` = ?)', ['1', '3', '4', '18']],
            'up to'       => ['-3', $prefix.'(`id` <= ?)', ['3']],
            'from'        => ['19-', $prefix.'(`id` >= ?)', ['19']],
            'empty parts' => [',,2,,', $prefix.'(`id` = ?)', ['2']],
        ];
    }

    #[DataProvider('idParamProvider')]
    public function test_PrepareBuilderIdsByParam(string $param, string $expectedSql, array $expectedBindings): void
    {
        $builder = ModelServiceTestItem::query();
        (new ModelService())->PrepareBuilderIdsByParam($builder, $param);

        $this->assertSame([$expectedSql, $expectedBindings], $this->sqlOf($builder));
    }

    public function test_empty_param_adds_no_condition(): void
    {
        $builder = ModelServiceTestItem::query();
        (new ModelService())->PrepareBuilderIdsByParam($builder, ' , ');

        $this->assertSame(['select * from `items`', []], $this->sqlOf($builder));
    }

    public function test_ids_are_grouped_so_other_conditions_still_apply(): void
    {
        $builder = ModelServiceTestItem::query()
            ->where('name', 'item2');
        (new ModelService())->PrepareBuilderIdsByParam($builder, '1,2');

        $this->assertSame([
            'select * from `items` where `name` = ? and (`id` = ? or `id` = ?)',
            ['item2', '1', '2'],
        ], $this->sqlOf($builder));
    }

    public function test_since_created_and_updated(): void
    {
        $service = new ModelService();

        $created = ModelServiceTestItem::query();
        $service->PrepareBuilderSinceCreated($created, '2026-01-01 00:00:00');
        $this->assertSame(['select * from `items` where `created_at` >= ?', ['2026-01-01 00:00:00']], $this->sqlOf($created));

        $updated = ModelServiceTestItem::query();
        $service->PrepareBuilderSinceUpdated($updated, '2026-04-01 00:00:00');
        $this->assertSame(['select * from `items` where `updated_at` >= ?', ['2026-04-01 00:00:00']], $this->sqlOf($updated));
    }
}

/**
 * Test model for ModelServiceTest.
 */
class ModelServiceTestItem extends Model
{
    protected $table = 'items';
}
