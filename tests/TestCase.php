<?php

namespace Modules\SystemBase\tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        try {
            DB::beginTransaction();
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [__METHOD__]);
            Log::error($e->getTraceAsString());
        }
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        try {
            DB::rollback();
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [__METHOD__]);
            Log::error($e->getTraceAsString());
        }
        parent::tearDown();
    }

    /**
     * @param $list
     * @param  callable  $callback
     * @return void
     */
    protected function runList($list, callable $callback): void
    {
        foreach ($list as $k => $data)
        {
            $callback($k, $data);
        }
    }
}
