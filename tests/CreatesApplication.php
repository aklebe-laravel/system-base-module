<?php
namespace Modules\SystemBase\tests;

use Illuminate\Contracts\Console\Kernel;

/**
 * Use this to avoid RuntimeException: A facade root has not been set.
 *
 */
trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
