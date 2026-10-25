<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\Base\BaseService;

class BaseServiceTest extends IsolatedTestCase
{
    public function test_indent_is_shared_and_never_negative(): void
    {
        $a = new BaseService();
        $b = new BaseService();

        $this->assertSame('', $a->getIndentString());
        $a->incrementIndent();
        $a->incrementIndent();
        $this->assertSame(2, $b->getIndentValue());
        $this->assertSame('    ', $b->getIndentString());

        $b->decrementIndent(5);
        $this->assertSame(0, $a->getIndentValue());
    }

    public function test_warning_and_error_fill_error_bag_and_log(): void
    {
        $service = new BaseService();
        $service->warning('w1');
        $service->error(['x' => 1]);
        $service->info('i1');

        $this->assertSame(['w1', ['x' => 1]], $service->getErrors());
        $this->assertSame(['w1'], $this->getLoggedMessages('warning'));
        $this->assertStringContainsString('[x] => 1', $this->getLoggedMessages('error')[0]);
        $this->assertSame(['i1'], $this->getLoggedMessages('info'));
    }

    public function test_debug_only_logs_in_debug_mode(): void
    {
        $service = new BaseService();
        $this->assertFalse($service->debugMode);
        $service->debug('hidden');
        $this->assertSame([], $this->getLoggedMessages('debug'));

        $this->setConfig('app.debug', true);
        $service = new BaseService();
        $service->incrementIndent();
        $service->debug('visible');
        $this->assertSame(['  visible'], $this->getLoggedMessages('debug'));
    }
}
