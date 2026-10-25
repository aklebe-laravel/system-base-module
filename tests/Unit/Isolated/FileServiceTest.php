<?php
declare(strict_types = 1);

namespace Modules\SystemBase\tests\Unit\Isolated;

use Modules\SystemBase\app\Services\FileService;
use PHPUnit\Framework\Attributes\DataProvider;

class FileServiceTest extends IsolatedTestCase
{
    /**
     * @var FileService
     */
    protected FileService $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = app('system_base_file');
    }

    public static function sanitizeProvider(): array
    {
        return [
            ['Äpfel & Birnen/Neue Liste.txt', 'aepfel_birnen/neue_liste_txt'],
            ['  __Hello--World__  ', 'hello_world'],
            ['a/_b_/c', 'a/b/c'],
            ['Straße', 'strasse'],
        ];
    }

    #[DataProvider('sanitizeProvider')]
    public function test_sanitize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->files->sanitize($input));
    }

    public static function validPathProvider(): array
    {
        return [
            ['a/b/c.txt', '', 'a/b/c.txt'],
            ['a//b///c', '', 'a/b/c'],
            ['a\\b\\\\c', '', 'a/b/c'],
            ['my:file?.txt', '_', 'my_file_.txt'],
            // '+' is a valid char and must not become a path separator
            ['c++/file+1.txt', '', 'c++/file+1.txt'],
        ];
    }

    #[DataProvider('validPathProvider')]
    public function test_getValidPath(string $input, string $replacement, string $expected): void
    {
        $this->assertSame($expected, $this->files->getValidPath($input, $replacement));
    }

    public function test_subPath_and_makeRelativePath(): void
    {
        $this->assertSame('/app/x.php', $this->files->subPath('/var/www/app/x.php', '/var/www'));
        $this->assertSame('app/x.php', $this->files->subPath('/var/www/app/x.php', '/var/www', true));
        $this->assertNull($this->files->subPath('/other/app/x.php', '/var/www'));

        $this->assertSame('my-path/temp1', $this->files->makeRelativePath('/root/my-path/temp1', '/root'));
        $this->assertSame('../../../my-path/temp1', $this->files->makeRelativePath('/root/my-path/temp1', '/root', '/root/a/b/c'));
        $this->assertNull($this->files->makeRelativePath('/other/x', '/root', '/root/a'));
    }

    /**
     * Creates root/f0.txt, root/d1/f1.txt, root/d1/d2/f2.txt, root/d1/d2/d3/f3.php
     */
    protected function makeTree(): string
    {
        $root = $this->makeTempDir();
        $this->makeFile($root.'/f0.txt', '0');
        $this->makeFile($root.'/d1/f1.txt', '1');
        $this->makeFile($root.'/d1/d2/f2.txt', '2');
        $this->makeFile($root.'/d1/d2/d3/f3.php', '3');

        return $root;
    }

    /**
     * Basenames found by runDirectoryFiles(), sorted.
     */
    protected function collect(string $root, int $deep = -1, array $white = [], array $black = [], string $delimiters = ''): array
    {
        $found = [];
        $this->files->runDirectoryFiles($root, function (string $file, array $info) use (&$found) {
            $found[] = $info['basename'];
        }, $deep, $white, $black, $delimiters);
        sort($found);

        return $found;
    }

    public function test_runDirectoryFiles_depth(): void
    {
        $root = $this->makeTree();

        $this->assertSame(['f0.txt'], $this->collect($root, 0));
        $this->assertSame(['f0.txt', 'f1.txt'], $this->collect($root, 1));
        $this->assertSame(['f0.txt', 'f1.txt', 'f2.txt'], $this->collect($root, 2));
        $this->assertSame(['f0.txt', 'f1.txt', 'f2.txt', 'f3.php'], $this->collect($root, -1));
    }

    public function test_runDirectoryFiles_depth_is_the_same_for_all_sibling_directories(): void
    {
        $root = $this->makeTempDir();
        foreach (['a', 'b', 'c'] as $dir) {
            $this->makeFile("$root/$dir/level1.txt");
            $this->makeFile("$root/$dir/sub/level2.txt");
        }

        // every directory gets the same depth, so no "level2" file at all
        $this->assertSame(['level1.txt', 'level1.txt', 'level1.txt'], $this->collect($root, 1));
    }

    public function test_runDirectoryFiles_white_and_blacklist(): void
    {
        $root = $this->makeTree();

        $this->assertSame(['f0.txt', 'f1.txt', 'f2.txt'], $this->collect($root, -1, [], ['\.php$'], '#'));
        // the whitelist is also checked for directories, so only root files can match here
        $this->assertSame(['f0.txt'], $this->collect($root, -1, ['\.txt$'], [], '#'));
        $this->assertSame([], $this->collect($root.'/missing'));
    }

    public function test_deleteFilesOlderThan_respects_blacklist_in_sub_directories(): void
    {
        $root = $this->makeTree();
        $this->makeFile($root.'/d1/keep.lock');

        $this->files->deleteFilesOlderThan($root, 0, '*', true, ['#\.lock$#']);

        $this->assertFileExists($root.'/d1/keep.lock');
        $this->assertFileDoesNotExist($root.'/f0.txt');
        $this->assertFileDoesNotExist($root.'/d1/d2/f2.txt');
        // empty folders were removed, d1 still has keep.lock
        $this->assertDirectoryDoesNotExist($root.'/d1/d2');
        $this->assertDirectoryExists($root.'/d1');
    }

    public function test_deleteFilesOlderThan_keeps_new_files(): void
    {
        $root = $this->makeTree();
        touch($root.'/f0.txt', time() - 3600);

        $this->files->deleteFilesOlderThan($root, 600);

        $this->assertFileDoesNotExist($root.'/f0.txt');
        $this->assertFileExists($root.'/d1/f1.txt');
    }

    public function test_copy_load_create_clean_and_delete(): void
    {
        $root = $this->makeTempDir();
        $source = $this->makeFile($root.'/source.txt', 'content');

        $this->assertTrue($this->files->copyPath($source, $root.'/new/dir/copy.txt'));
        $this->assertSame('content', $this->files->loadFile($root.'/new/dir/copy.txt'));
        $this->assertFalse($this->files->loadFile($root.'/missing.txt'));
        $this->assertFalse($this->files->loadFile($root.'/new'));

        $this->assertFalse($this->files->copyPath($root.'/missing.txt', $root.'/x.txt'));
        $this->assertNotEmpty($this->files->getErrors());

        $this->assertTrue($this->files->createDir($root.'/a/b'));
        $this->assertTrue($this->files->createDir($root.'/a/b'));
    }
}
