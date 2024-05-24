<?php

namespace Modules\SystemBase\tests\Feature;

use Modules\SystemBase\app\Services\GitService;
use Modules\SystemBase\tests\TestCase;

class GitTest extends TestCase
{
    /**
     * Testing results of
     * GitService::findSatisfiedVersion()
     * GitService::findSatisfiedBranch()
     */
    public function test_constraint_satisfied_versions(): void
    {
        $testList = [
            [
                'test'   => '*',
                'type'   => 'version',
                'list'   => ['v1.0.0', 'v1.2.0', 'v1.0.0', 'v2.0.0', 'v5.0.1'],
                'expect' => 'v5.0.1',
            ],
            [
                'test'   => '^1.1.0',
                'type'   => 'version',
                'list'   => ['v1.0.0', 'v1.2.0', 'v1.0.0', 'v2.0.0', 'v5.0.1'],
                'expect' => 'v1.2.0',
            ],
            [
                'test'   => 'dev-master',
                'type'   => 'branch',
                'list'   => ['v1.0.0', 'v1.2.0', 'master', 'test_branch', 'v1.0.0', 'v2.0.0', 'v5.0.1'],
                'expect' => 'master',
            ],
        ];

        /** @var GitService $gitService */
        $gitService = app(GitService::class);
        foreach ($testList as $tesItem) {
            $constraint = data_get($tesItem, 'test');
            $type = data_get($tesItem, 'type');
            $expected = data_get($tesItem, 'expect');

            switch ($type) {
                case 'version':
                    $result = $gitService->findSatisfiedVersion(data_get($tesItem, 'list'), $constraint);
                    break;
                case 'branch':
                    $result = $gitService->findSatisfiedBranch(data_get($tesItem, 'list'), $constraint);
                    break;
            }

            if ($result !== $expected) {
                $this->assertTrue(false,
                    sprintf("Result for '%s': '%s', but expected was '%s'.", $constraint, $result, $expected));
            }
        }

        $this->assertTrue(true);
    }
}
