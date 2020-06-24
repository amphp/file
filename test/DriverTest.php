<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\FilesystemException;

abstract class DriverTest extends FilesystemTest
{
    public function testListFiles()
    {
        $fixtureDir = Fixture::path();
        $actual = yield File\scandir($fixtureDir);
        $expected = ["dir", "dirlink", "fifo", "fifolink", "file", "filelink", "linkloop"];
        $this->assertSame($expected, $actual);
    }

    public function testListFilesThrowsIfPathNotADirectory(): \Generator
    {
        $this->expectException(FilesystemException::class);

        yield File\scandir(__FILE__);
    }

    public function testListFilesThrowsIfPathDoesntExist(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $path = Fixture::path() . "/nonexistent";
        yield File\scandir($path);
    }

    public function testCreateSymlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        $this->assertNull(yield File\symlink($original, $link));
        $this->assertTrue(\is_link($link));
        yield File\unlink($link);
    }

    public function testCreateSymlinkFailWhenLinkExists(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        yield File\symlink($path, $path);
    }

    public function testCreateHardlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/hardlink.txt";
        $this->assertNull(yield File\link($original, $link));
        $this->assertFileExists($link);
        $this->assertFalse(\is_link($link));
        yield File\unlink($link);
    }

    public function testCreateHardlinkFailWhenLinkExists(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        yield File\link($path, $path);
    }

    public function testResolveSymlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        \symlink($original, $link);

        $this->assertSame($original, yield File\readlink($link));
    }

    public function symlinkPathProvider(): array
    {
        return [
            'nonExistingPath' => [
                static function () {
                    return Fixture::path() . '/' . \uniqid('amphp-test-', true);
                },
            ],
            'notLink' => [
                static function () {
                    return Fixture::path();
                },
            ],
        ];
    }

    /**
     * @dataProvider symlinkPathProvider
     *
     * @param \Closure $linkResolver
     * @return \Generator
     */
    public function testResolveSymlinkError(\Closure $linkResolver): \Generator
    {
        $this->expectException(FilesystemException::class);

        $link = $linkResolver();

        yield File\readlink($link);
    }

    public function testLinkStatus(): \Generator
    {
        $fixtureDir = Fixture::path();

        $target = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        yield File\symlink($target, $link);
        $this->assertIsArray(yield File\lstat($link));
        yield File\unlink($link);
    }

    public function testStatus(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = yield File\stat($path);
        $this->assertNotNull(File\StatCache::get($path));
        $this->assertIsArray($stat);
        $this->assertSameStatus(\stat($path), $stat);
    }

    public function testDirectoryStatus(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = yield File\stat("{$fixtureDir}/dir");
        $this->assertIsArray($stat);
        $this->assertSameStatus(\stat("{$fixtureDir}/dir"), $stat);
    }

    public function testNonexistentPathStatusResolvesToNull(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = yield File\stat("{$fixtureDir}/nonexistent");
        $this->assertNull($stat);
    }

    public function testExists(): \Generator
    {
        $fixtureDir = Fixture::path();
        $this->assertFalse(yield File\exists("{$fixtureDir}/nonexistent"));
        $this->assertTrue(yield File\exists("{$fixtureDir}/file"));
    }

    public function testRead(): \Generator
    {
        $fixtureDir = Fixture::path();
        $this->assertSame("small", yield File\get("{$fixtureDir}/file"));
    }

    public function testSize(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = (yield File\stat($path));
        $size = $stat["size"];
        File\StatCache::clear($path);
        $this->assertSame($size, (yield File\size($path)));
    }

    public function testSizeFailsOnNonexistentPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        yield File\size($path);
    }

    public function testSizeFailsOnDirectoryPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/dir";
        $this->assertTrue(yield File\isdir($path));
        File\StatCache::clear($path);
        yield File\size($path);
    }

    /**
     * @dataProvider dataForDirectoryCheck
     */
    public function testIsDirectory(bool $expectedResult, string $name): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, yield File\isdir($path));
    }

    public function dataForDirectoryCheck(): \Generator
    {
        yield 'file' => [false, 'file'];
        yield 'filelink' => [false, 'filelink'];
        yield 'dir' => [true, 'dir'];
        yield 'dirlink' => [true, 'dirlink'];
        if (\extension_loaded('posix')) {
            yield 'fifo' => [false, 'fifo'];
            yield 'fifolink' => [false, 'fifolink'];
        }
        yield 'linkloop' => [false, 'linkloop'];
        yield 'nonexistent' => [false, 'nonexistent'];
    }

    /**
     * @dataProvider dataForFileCheck
     */
    public function testIsFile(bool $expectedResult, string $name): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, yield File\isfile($path));
    }

    public function dataForFileCheck(): \Generator
    {
        yield 'file' => [true, 'file'];
        yield 'filelink' => [true, 'filelink'];
        yield 'dir' => [false, 'dir'];
        yield 'dirlink' => [false, 'dirlink'];
        if (\extension_loaded('posix')) {
            yield 'fifo' => [false, 'fifo'];
            yield 'fifolink' => [false, 'fifolink'];
        }
        yield 'linkloop' => [false, 'linkloop'];
        yield 'nonexistent' => [false, 'nonexistent'];
    }

    /**
     * @dataProvider dataForSymlinkCheck
     */
    public function testIsSymlink(bool $expectedResult, string $name): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, yield File\isSymlink($path));
    }

    public function dataForSymlinkCheck(): \Generator
    {
        yield 'file' => [false, 'file'];
        yield 'filelink' => [true, 'filelink'];
        yield 'dir' => [false, 'dir'];
        yield 'dirlink' => [true, 'dirlink'];
        if (\extension_loaded('posix')) {
            yield 'fifo' => [false, 'fifo'];
            yield 'fifolink' => [true, 'fifolink'];
        }
        yield 'linkloop' => [true, 'linkloop'];
        yield 'nonexistent' => [false, 'nonexistent'];
    }

    public function testMove(): \Generator
    {
        $fixtureDir = Fixture::path();

        $contents1 = "rename test";
        $old = "{$fixtureDir}/rename1.txt";
        $new = "{$fixtureDir}/rename2.txt";

        yield File\put($old, $contents1);
        $this->assertNull(yield File\rename($old, $new));
        $contents2 = (yield File\get($new));
        yield File\unlink($new);

        $this->assertSame($contents1, $contents2);
    }

    public function testMoveFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\rename($path, $path);
    }

    public function testDeleteFile(): \Generator
    {
        $fixtureDir = Fixture::path();
        $toUnlink = "{$fixtureDir}/unlink";
        yield File\stat($toUnlink);
        yield File\put($toUnlink, "unlink me");
        $this->assertNull(File\StatCache::get($toUnlink));
        $this->assertNull(yield File\unlink($toUnlink));
        $this->assertNull(yield File\stat($toUnlink));
    }

    public function testDeleteFileFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\unlink($path);
    }

    public function testDeleteFileFailsOnDirectory(): \Generator
    {
        $fixtureDir = Fixture::path();
        $dir = "{$fixtureDir}/newdir";
        yield File\mkdir($dir);

        $this->expectException(FilesystemException::class);

        yield File\unlink($dir);
    }

    public function testCreateAndDeleteDirectory(): \Generator
    {
        $fixtureDir = Fixture::path();

        $dir = "{$fixtureDir}/newdir";

        \umask(0022);

        $this->assertNull(yield File\mkdir($dir));
        $stat = yield File\stat($dir);
        $this->assertSame('0755', $this->getPermissionsFromStatus($stat));
        $this->assertNull(yield File\rmdir($dir));
        $this->assertNull(File\StatCache::get($dir));
        $this->assertNull(yield File\stat($dir));

        // test for 0, because previous array_filter made that not work
        $dir = "{$fixtureDir}/newdir/with/recursive/creation/0/1/2";

        $this->assertNull(yield File\mkdir($dir, 0764, true));
        $stat = yield File\stat($dir);
        $this->assertSame('0744', $this->getPermissionsFromStatus($stat));
    }

    public function testCreateDirectoryFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\mkdir($path);
    }

    public function testDeleteDirectoryFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\rmdir($path);
    }

    public function testDeleteDirectoryFailsOnFile(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";

        $this->expectException(FilesystemException::class);

        yield File\rmdir($path);
    }

    public function testMtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = (yield File\stat($path));
        $statMtime = $stat["mtime"];
        File\StatCache::clear($path);
        $this->assertSame($statMtime, (yield File\mtime($path)));
    }

    public function testMtimeFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\mtime($path);
    }

    public function testAtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = (yield File\stat($path));
        $statAtime = $stat["atime"];
        File\StatCache::clear($path);
        $this->assertSame($statAtime, (yield File\atime($path)));
    }

    public function testAtimeFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\atime($path);
    }

    public function testCtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = (yield File\stat($path));
        $statCtime = $stat["ctime"];
        File\StatCache::clear($path);
        $this->assertSame($statCtime, (yield File\ctime($path)));
    }

    public function testCtimeFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\ctime($path);
    }

    /**
     * @group slow
     */
    public function testTouch(): \Generator
    {
        $fixtureDir = Fixture::path();

        $touch = "{$fixtureDir}/touch";
        yield File\put($touch, "touch me");

        $oldStat = yield File\stat($touch);
        $this->assertNull(yield File\touch($touch, \time() + 10, \time() + 20));
        $this->assertNull(File\StatCache::get($touch));
        $newStat = yield File\stat($touch);
        yield File\unlink($touch);

        $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
        $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
    }

    public function testTouchFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\touch($path);
    }

    public function testChangePermissions(): \Generator
    {
        $fixtureDir = Fixture::path();

        $path = "{$fixtureDir}/file";
        $stat = yield File\stat($path);
        $this->assertNotSame('0777', \substr(\decoct($stat['mode']), -4));
        $this->assertNull(yield File\chmod($path, 0777));
        $this->assertNull(File\StatCache::get($path));
        $stat = yield File\stat($path);
        $this->assertSame('0777', \substr(\decoct($stat['mode']), -4));
    }

    public function testChangePermissionsFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\chmod($path, 0777);
    }

    public function testChangeOwner(): \Generator
    {
        $fixtureDir = Fixture::path();

        $path = "{$fixtureDir}/file";
        yield File\stat($path);
        $user = \fileowner($path);
        $this->assertNull(yield File\chown($path, $user));
        $this->assertNull(File\StatCache::get($path));
    }

    public function testChangeOwnerFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield File\chown($path, 0);
    }

    private function assertSameStatus(array $expected, array $actual): void
    {
        $filter = function (array $stat) {
            $filtered = \array_filter(
                $stat,
                function (string $key): bool {
                    return !\is_numeric($key);
                },
                ARRAY_FILTER_USE_KEY
            );

            \ksort($filtered);

            return $filtered;
        };

        $this->assertSame($filter($expected), $filter($actual));
    }

    /**
     * @param array $stat
     *
     * @return string
     */
    private function getPermissionsFromStatus(array $stat): string
    {
        return \substr(\decoct($stat["mode"]), 1);
    }
}
