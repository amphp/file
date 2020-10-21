<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\FilesystemException;

abstract class DriverTest extends FilesystemTest
{
    /** @var File\Filesystem */
    protected $driver;

    public function testListFiles(): \Generator
    {
        $fixtureDir = Fixture::path();
        $actual = yield $this->driver->listFiles($fixtureDir);
        $expected = ["dir", "dirlink", "fifo", "fifolink", "file", "filelink", "linkloop"];
        $this->assertSame($expected, $actual);
    }

    public function testListFilesThrowsIfPathNotADirectory(): \Generator
    {
        $this->expectException(FilesystemException::class);

        yield $this->driver->listFiles(__FILE__);
    }

    public function testListFilesThrowsIfPathDoesntExist(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $path = Fixture::path() . "/nonexistent";
        yield $this->driver->listFiles($path);
    }

    public function testCreateSymlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        $this->assertNull(yield $this->driver->createSymlink($original, $link));
        $this->assertTrue(\is_link($link));
        yield $this->driver->deleteFile($link);
    }

    public function testCreateSymlinkFailWhenLinkExists(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        yield $this->driver->createSymlink($path, $path);
    }

    public function testCreateHardlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/hardlink.txt";
        $this->assertNull(yield $this->driver->createHardlink($original, $link));
        $this->assertFileExists($link);
        $this->assertFalse(\is_link($link));
        yield $this->driver->deleteFile($link);
    }

    public function testCreateHardlinkFailWhenLinkExists(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        yield $this->driver->createHardlink($path, $path);
    }

    public function testResolveSymlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        \symlink($original, $link);

        $this->assertSame($original, yield $this->driver->resolveSymlink($link));
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
     *
     * @return \Generator
     */
    public function testResolveSymlinkError(\Closure $linkResolver): \Generator
    {
        $this->expectException(FilesystemException::class);

        $link = $linkResolver();

        yield $this->driver->resolveSymlink($link);
    }

    public function testLinkStatus(): \Generator
    {
        $fixtureDir = Fixture::path();

        $target = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        yield $this->driver->createSymlink($target, $link);
        $this->assertIsArray(yield $this->driver->getLinkStatus($link));
        yield $this->driver->deleteFile($link);
    }

    public function testStatus(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = yield $this->driver->getStatus($path);
        $this->assertIsArray($stat);
        $this->assertSameStatus(\stat($path), $stat);
    }

    public function testDirectoryStatus(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = yield $this->driver->getStatus("{$fixtureDir}/dir");
        $this->assertIsArray($stat);
        $this->assertSameStatus(\stat("{$fixtureDir}/dir"), $stat);
    }

    public function testNonexistentPathStatusResolvesToNull(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = yield $this->driver->getStatus("{$fixtureDir}/nonexistent");
        $this->assertNull($stat);
    }

    public function testExists(): \Generator
    {
        $fixtureDir = Fixture::path();
        $this->assertFalse(yield $this->driver->exists("{$fixtureDir}/nonexistent"));
        $this->assertTrue(yield $this->driver->exists("{$fixtureDir}/file"));
    }

    public function testRead(): \Generator
    {
        $fixtureDir = Fixture::path();
        $this->assertSame("small", yield $this->driver->read("{$fixtureDir}/file"));
    }

    public function testSize(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = (yield $this->driver->getStatus($path));
        $size = $stat["size"];
        $this->assertSame($size, yield $this->driver->getSize($path));
    }

    public function testSizeFailsOnNonexistentPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        yield $this->driver->getSize($path);
    }

    public function testSizeFailsOnDirectoryPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/dir";
        $this->assertTrue(yield $this->driver->isDirectory($path));
        yield $this->driver->getSize($path);
    }

    /**
     * @dataProvider dataForDirectoryCheck
     */
    public function testIsDirectory(bool $expectedResult, string $name): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, yield $this->driver->isDirectory($path));
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
        $this->assertSame($expectedResult, yield $this->driver->isFile($path));
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
        $this->assertSame($expectedResult, yield $this->driver->isSymlink($path));
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

        yield $this->driver->write($old, $contents1);
        $this->assertNull(yield $this->driver->move($old, $new));
        $contents2 = yield $this->driver->read($new);
        yield $this->driver->deleteFile($new);

        $this->assertSame($contents1, $contents2);
    }

    public function testMoveFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->move($path, $path);
    }

    public function testDeleteFile(): \Generator
    {
        $fixtureDir = Fixture::path();
        $toUnlink = "{$fixtureDir}/unlink";
        yield $this->driver->getStatus($toUnlink);
        yield $this->driver->write($toUnlink, "unlink me");
        $this->assertNull(yield $this->driver->deleteFile($toUnlink));
        $this->assertNull(yield $this->driver->getStatus($toUnlink));
    }

    public function testDeleteFileFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->deleteFile($path);
    }

    public function testDeleteFileFailsOnDirectory(): \Generator
    {
        $fixtureDir = Fixture::path();
        $dir = "{$fixtureDir}/newdir";
        yield $this->driver->createDirectory($dir);

        $this->expectException(FilesystemException::class);

        yield $this->driver->deleteFile($dir);
    }

    public function testCreateAndDeleteDirectory(): \Generator
    {
        $fixtureDir = Fixture::path();

        $dir = "{$fixtureDir}/newdir";

        \umask(0022);

        $this->assertNull(yield $this->driver->createDirectory($dir));
        $stat = yield $this->driver->getStatus($dir);
        $this->assertSame('0755', $this->getPermissionsFromStatus($stat));
        $this->assertNull(yield $this->driver->deleteDirectory($dir));
        $this->assertNull(yield $this->driver->getStatus($dir));

        // test for 0, because previous array_filter made that not work
        $dir = "{$fixtureDir}/newdir/with/recursive/creation/0/1/2";

        $this->assertNull(yield $this->driver->createDirectoryRecursively($dir, 0764));
        $stat = yield $this->driver->getStatus($dir);
        $this->assertSame('0744', $this->getPermissionsFromStatus($stat));
    }

    public function testCreateDirectoryFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->createDirectory($path);
    }

    public function testDeleteDirectoryFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->deleteDirectory($path);
    }

    public function testDeleteDirectoryFailsOnFile(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";

        $this->expectException(FilesystemException::class);

        yield $this->driver->deleteDirectory($path);
    }

    public function testMtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = yield $this->driver->getStatus($path);
        $statMtime = $stat["mtime"];
        $this->assertSame($statMtime, yield $this->driver->getModificationTime($path));
    }

    public function testMtimeFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->getModificationTime($path);
    }

    public function testAtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = yield $this->driver->getStatus($path);
        $statAtime = $stat["atime"];
        $this->assertSame($statAtime, yield $this->driver->getAccessTime($path));
    }

    public function testAtimeFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->getAccessTime($path);
    }

    public function testCtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = yield $this->driver->getStatus($path);
        $statCtime = $stat["ctime"];
        $this->assertSame($statCtime, yield $this->driver->getCreationTime($path));
    }

    public function testCtimeFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->getCreationTime($path);
    }

    /**
     * @group slow
     */
    public function testTouch(): \Generator
    {
        $fixtureDir = Fixture::path();

        $touch = "{$fixtureDir}/touch";
        yield $this->driver->write($touch, "touch me");

        $oldStat = yield $this->driver->getStatus($touch);
        $this->assertNull(yield $this->driver->touch($touch, \time() + 10, \time() + 20));
        $newStat = yield $this->driver->getStatus($touch);
        yield $this->driver->deleteFile($touch);

        $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
        $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
    }

    public function testTouchFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->touch($path);
    }

    public function testChangePermissions(): \Generator
    {
        $fixtureDir = Fixture::path();

        $path = "{$fixtureDir}/file";
        $stat = yield $this->driver->getStatus($path);
        $this->assertNotSame('0777', \substr(\decoct($stat['mode']), -4));
        $this->assertNull(yield $this->driver->changePermissions($path, 0777));
        $stat = yield $this->driver->getStatus($path);
        $this->assertSame('0777', \substr(\decoct($stat['mode']), -4));
    }

    public function testChangePermissionsFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->changePermissions($path, 0777);
    }

    public function testChangeOwner(): \Generator
    {
        $fixtureDir = Fixture::path();

        $path = "{$fixtureDir}/file";
        yield $this->driver->getStatus($path);
        $user = \fileowner($path);
        $this->assertNull(yield $this->driver->changeOwner($path, $user, null));
    }

    public function testChangeOwnerFailsOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        yield $this->driver->changeOwner($path, 0, null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new File\Filesystem($this->createDriver());
    }

    abstract protected function createDriver(): File\Driver;

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
