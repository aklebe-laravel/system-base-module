<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Models\JsonViewResponse;
use Modules\SystemBase\app\Rules\DirectoryRule;
use Modules\SystemBase\app\Rules\SubPathRule;
use Modules\SystemBase\app\Services\PathService;
use Modules\SystemBase\app\Services\PhpToJsService;

/**
 * Tests for small helper classes.
 */
class SmallServicesTest extends IsolatedTestCase
{
    public function test_php_to_js(): void
    {
        $service = new PhpToJsService();
        $service->addData('a.b', 1);
        $service->addData('c', ['x']);

        $this->assertTrue($service->has('a.b'));
        $this->assertFalse($service->has('a.x'));
        $this->assertSame(1, $service->get('a.b'));
        $this->assertSame(['a' => ['b' => 1], 'c' => ['x']], $service->get());
        $this->assertSame('{"a":{"b":1},"c":["x"]}', $service->toJson());
    }

    public function test_path_combine(): void
    {
        $this->assertSame('/var'.DIRECTORY_SEPARATOR.'www'.DIRECTORY_SEPARATOR.'app', (new PathService())->combine('/var', 'www', '', 'app'));
    }

    public function test_json_view_response(): void
    {
        $response = new JsonViewResponse('hello');
        $this->assertSame('hello', $response->getMessage());
        $this->assertFalse($response->hasErrors());

        $response->setData('a.b', 1);
        $this->assertSame(1, $response->getData('a.b'));
        $response->setData(['x' => 2]);
        $this->assertSame(2, $response->getData('x'));
        $this->assertNull($response->getData('a.b'));

        $response->addMessageToErrorList('e1');
        $this->assertTrue($response->hasErrors());
        $this->assertSame(200, $response->responseStatusCode);

        $response->setErrorMessage('failed');
        $this->assertSame(422, $response->responseStatusCode);
        $this->assertSame(['e1', 'failed'], $response->getErrors());

        $target = new JsonViewResponse();
        $response->transportAllMessagesToNewErrorList($target);
        $this->assertSame(['failed', 'e1', 'failed'], $target->getErrors());

        $response->setMessage('x', 500);
        $this->assertSame(500, $response->responseStatusCode);
    }

    /**
     * Runs a validation rule and returns the failure messages.
     */
    protected function validate(object $rule, mixed $value): array
    {
        $messages = [];
        $rule->validate('attr', $value, function (string $message) use (&$messages) {
            $messages[] = $message;
        });

        return $messages;
    }

    public function test_directory_rule(): void
    {
        $this->assertSame([], $this->validate(new DirectoryRule(), $this->makeTempDir()));
        $this->assertCount(1, $this->validate(new DirectoryRule(), '/definitely/missing/dir'));
        $this->assertCount(1, $this->validate(new DirectoryRule(), ['array']));
    }

    public function test_sub_path_rule(): void
    {
        $this->assertSame([], $this->validate(new SubPathRule(), 'a/b/c.txt'));
        $this->assertCount(1, $this->validate(new SubPathRule(), 'a//b'));
        $this->assertCount(1, $this->validate(new SubPathRule(), 'a?b'));
    }
}
