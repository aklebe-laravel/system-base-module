<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\GitService;

class GitServiceTest extends IsolatedTestCase
{
    public function test_findSatisfiedVersion_picks_highest_match(): void
    {
        $git = new GitService();
        $tags = ['1.0.0', 'v1.2.0', '1.10.1', '2.0.0', '0.9'];

        $this->assertSame('1.10.1', $git->findSatisfiedVersion($tags, '^1.0'));
        $this->assertSame('2.0.0', $git->findSatisfiedVersion($tags, '>=1.0'));
        $this->assertSame('0.9', $git->findSatisfiedVersion($tags, '<1.0'));
        $this->assertNull($git->findSatisfiedVersion($tags, '^3.0'));
        $this->assertSame('1.10.1', $git->findBestVersion($tags, '~1.1'));
    }

    public function test_findSatisfiedVersion_ignores_invalid_tags_and_constraints(): void
    {
        $git = new GitService();

        $this->assertSame('1.0.1', $git->findSatisfiedVersion(['latest', 'test-tag', '1.0.1'], '^1.0'));
        $this->assertNull($git->findSatisfiedVersion(['1.0.1'], 'dev-master'));
        $this->assertNull($git->findSatisfiedVersion(['1.0.1'], 'not a constraint !!'));
        $this->assertNull($git->findSatisfiedVersion([], '^1.0'));
    }

    public function test_findSatisfiedBranch(): void
    {
        $git = new GitService();
        $branches = ['HEAD -> origin/master', 'develop', 'master', 'feature/x'];

        $this->assertSame('master', $git->findSatisfiedBranch($branches, 'dev-master'));
        $this->assertSame('develop', $git->findSatisfiedBranch($branches, 'develop'));
        $this->assertSame('feature/x', $git->findSatisfiedBranch($branches, 'feature/.*'));
        $this->assertNull($git->findSatisfiedBranch($branches, 'release'));
    }
}
