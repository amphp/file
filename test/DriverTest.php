<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\FilesystemException;

abstract class DriverTest extends FilesystemTest
{
    protected File\Filesystem $driver;

    public function testListFiles()
    {
        $fixtureDir = Fixture::path();
        $actual = $this->driver->listFiles($fixtureDir);
        $expected = ["dir", "dirlink", "fifo", "fifolink", "file", "filelink", "linkloop"];
        $this->assertSame($expected, $actual);
    }

    public function testListFilesThrowsIfPathNotADirectory()
    {
        $this->expectException(FilesystemException::class);

        $this->driver->listFiles(__FILE__);
    }

    public function testListFilesThrowsIfPathDoesntExist()
    {
        $this->expectException(FilesystemException::class);

        $path = Fixture::path() . "/nonexistent";
        $this->driver->listFiles($path);
    }

    public function testCreateSymlink()
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        $this->assertNull($this->driver->createSymlink($original, $link));
        $this->assertTrue(\is_link($link));
        $this->driver->deleteFile($link);
    }

    public function testCreateSymlinkFailWhenLinkExists()
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $this->driver->createSymlink($path, $path);
    }

    public function testCreateHardlink()
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/hardlink.txt";
        $this->assertNull($this->driver->createHardlink($original, $link));
        $this->assertFileExists($link);
        $this->assertFalse(\is_link($link));
        $this->driver->deleteFile($link);
    }

    public function testCreateHardlinkFailWhenLinkExists()
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $this->driver->createHardlink($path, $path);
    }

    public function testResolveSymlink()
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        \symlink($original, $link);

        $this->assertSame($original, $this->driver->resolveSymlink($link));
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
    public function testResolveSymlinkError(\Closure $linkResolver)
    {
        $this->expectException(FilesystemException::class);

        $link = $linkResolver();

        $this->driver->resolveSymlink($link);
    }

    public function testLinkStatus()
    {
        $fixtureDir = Fixture::path();

        $target = "{$fixtureDir}/file";
        $link = "{$fixtureDir}/symlink.txt";
        $this->driver->createSymlink($target, $link);
        $this->assertIsArray($this->driver->getLinkStatus($link));
        $this->driver->deleteFile($link);
    }

    public function testStatus()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = $this->driver->getStatus($path);
        $this->assertIsArray($stat);
        $this->assertSameStatus(\stat($path), $stat);
    }

    public function testDirectoryStatus()
    {
        $fixtureDir = Fixture::path();
        $stat = $this->driver->getStatus("{$fixtureDir}/dir");
        $this->assertIsArray($stat);
        $this->assertSameStatus(\stat("{$fixtureDir}/dir"), $stat);
    }

    public function testNonexistentPathStatusResolvesToNull()
    {
        $fixtureDir = Fixture::path();
        $stat = $this->driver->getStatus("{$fixtureDir}/nonexistent");
        $this->assertNull($stat);
    }

    public function testExists()
    {
        $fixtureDir = Fixture::path();
        $this->assertFalse($this->driver->exists("{$fixtureDir}/nonexistent"));
        $this->assertTrue($this->driver->exists("{$fixtureDir}/file"));
    }

    public function testRead()
    {
        $fixtureDir = Fixture::path();
        $this->assertSame("small", $this->driver->read("{$fixtureDir}/file"));
    }

    public function testSize()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = ($this->driver->getStatus($path));
        $size = $stat["size"];
        $this->assertSame($size, $this->driver->getSize($path));
    }

    public function testSizeFailsOnNonexistentPath()
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        $this->driver->getSize($path);
    }

    public function testSizeFailsOnDirectoryPath()
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/dir";
        $this->assertTrue($this->driver->isDirectory($path));
        $this->driver->getSize($path);
    }

    /**
     * @dataProvider dataForDirectoryCheck
     */
    public function testIsDirectory(bool $expectedResult, string $name)
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, $this->driver->isDirectory($path));
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
    public function testIsFile(bool $expectedResult, string $name)
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, $this->driver->isFile($path));
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
    public function testIsSymlink(bool $expectedResult, string $name)
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/{$name}";
        $this->assertSame($expectedResult, $this->driver->isSymlink($path));
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

    public function testMove()
    {
        $fixtureDir = Fixture::path();

        $contents1 = "rename test";
        $old = "{$fixtureDir}/rename1.txt";
        $new = "{$fixtureDir}/rename2.txt";

        $this->driver->write($old, $contents1);
        $this->assertNull($this->driver->move($old, $new));
        $contents2 = $this->driver->read($new);
        $this->driver->deleteFile($new);

        $this->assertSame($contents1, $contents2);
    }

    public function testMoveFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->move($path, $path);
    }

    public function testDeleteFile()
    {
        $fixtureDir = Fixture::path();
        $toUnlink = "{$fixtureDir}/unlink";
        $this->driver->getStatus($toUnlink);
        $this->driver->write($toUnlink, "unlink me");
        $this->assertNull($this->driver->deleteFile($toUnlink));
        $this->assertNull($this->driver->getStatus($toUnlink));
    }

    public function testDeleteFileFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->deleteFile($path);
    }

    public function testDeleteFileFailsOnDirectory()
    {
        $fixtureDir = Fixture::path();
        $dir = "{$fixtureDir}/newdir";
        $this->driver->createDirectory($dir);

        $this->expectException(FilesystemException::class);

        $this->driver->deleteFile($dir);
    }

    public function testCreateAndDeleteDirectory()
    {
        $fixtureDir = Fixture::path();

        $dir = "{$fixtureDir}/newdir";

        \umask(0022);

        $this->assertNull($this->driver->createDirectory($dir));
        $stat = $this->driver->getStatus($dir);
        $this->assertSame('0755', $this->getPermissionsFromStatus($stat));
        $this->assertNull($this->driver->deleteDirectory($dir));
        $this->assertNull($this->driver->getStatus($dir));

        // test for 0, because previous array_filter made that not work
        $dir = "{$fixtureDir}/newdir/with/recursive/creation/0/1/2";

        $this->assertNull($this->driver->createDirectoryRecursively($dir, 0764));
        $stat = $this->driver->getStatus($dir);
        $this->assertSame('0744', $this->getPermissionsFromStatus($stat));
    }

    public function testCreateDirectoryFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->createDirectory($path);
    }

    public function testDeleteDirectoryFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->deleteDirectory($path);
    }

    public function testDeleteDirectoryFailsOnFile()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";

        $this->expectException(FilesystemException::class);

        $this->driver->deleteDirectory($path);
    }

    public function testMtime()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = $this->driver->getStatus($path);
        $statMtime = $stat["mtime"];
        $this->assertSame($statMtime, $this->driver->getModificationTime($path));
    }

    public function testMtimeFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->getModificationTime($path);
    }

    public function testAtime()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = $this->driver->getStatus($path);
        $statAtime = $stat["atime"];
        $this->assertSame($statAtime, $this->driver->getAccessTime($path));
    }

    public function testAtimeFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->getAccessTime($path);
    }

    public function testCtime()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/file";
        $stat = $this->driver->getStatus($path);
        $statCtime = $stat["ctime"];
        $this->assertSame($statCtime, $this->driver->getCreationTime($path));
    }

    public function testCtimeFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->getCreationTime($path);
    }

    /**
     * @group slow
     */
    public function testTouch()
    {
        $fixtureDir = Fixture::path();

        $touch = "{$fixtureDir}/touch";
        $this->driver->write($touch, "touch me");

        $oldStat = $this->driver->getStatus($touch);
        $this->assertNull($this->driver->touch($touch, \time() + 10, \time() + 20));
        $newStat = $this->driver->getStatus($touch);
        $this->driver->deleteFile($touch);

        $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
        $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
    }

    public function testTouchFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->touch($path);
    }

    public function testChangePermissions()
    {
        $fixtureDir = Fixture::path();

        $path = "{$fixtureDir}/file";
        $stat = $this->driver->getStatus($path);
        $this->assertNotSame('0777', \substr(\decoct($stat['mode']), -4));
        $this->assertNull($this->driver->changePermissions($path, 0777));
        $stat = $this->driver->getStatus($path);
        $this->assertSame('0777', \substr(\decoct($stat['mode']), -4));
    }

    public function testChangePermissionsFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->changePermissions($path, 0777);
    }

    public function testChangeOwner()
    {
        $fixtureDir = Fixture::path();

        $path = "{$fixtureDir}/file";
        $this->driver->getStatus($path);
        $user = \fileowner($path);
        $this->assertNull($this->driver->changeOwner($path, $user, null));
    }

    public function testChangeOwnerFailsOnNonexistentPath()
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";

        $this->expectException(FilesystemException::class);

        $this->driver->changeOwner($path, 0, null);
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
