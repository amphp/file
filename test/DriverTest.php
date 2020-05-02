<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\FilesystemException;
use Amp\Promise;

abstract class DriverTest extends FilesystemTest
{
    public function testScandir()
    {
        $fixtureDir = Fixture::path();
        $actual = yield File\scandir($fixtureDir);
        $expected = ["dir", "small.txt"];
        $this->assertSame($expected, $actual);
    }

    public function testScandirThrowsIfPathNotADirectory(): Promise
    {
        $this->expectException(FilesystemException::class);

        return File\scandir(__FILE__);
    }

    public function testScandirThrowsIfPathDoesntExist(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $path = Fixture::path() . "/nonexistent";
        (yield File\scandir($path));
    }

    public function testSymlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/small.txt";
        $link = "{$fixtureDir}/symlink.txt";
        $this->assertTrue(yield File\symlink($original, $link));
        $this->assertTrue(\is_link($link));
        yield File\unlink($link);
    }

    public function testReadlink(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/small.txt";
        $link = "{$fixtureDir}/symlink.txt";
        \symlink($original, $link);

        $this->assertSame($original, yield File\readlink($link));
    }

    public function readlinkPathProvider(): array
    {
        return [
            'nonExistingPath' => [function () {
                return Fixture::path() . '/' . \uniqid();
            }],
            'notLink' => [function () {
                return Fixture::path();
            }],
        ];
    }

    /**
     * @dataProvider readlinkPathProvider
     *
     * @param \Closure $linkResolver
     */
    public function testReadlinkError(\Closure $linkResolver): \Generator
    {
        $this->expectException(FilesystemException::class);

        $link = $linkResolver();

        yield File\readlink($link);
    }

    public function testLstat(): \Generator
    {
        $fixtureDir = Fixture::path();

        $target = "{$fixtureDir}/small.txt";
        $link = "{$fixtureDir}/symlink.txt";
        $this->assertTrue(yield File\symlink($target, $link));
        $this->assertIsArray(yield File\lstat($link));
        yield File\unlink($link);
    }

    public function testFileStat(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = (yield File\stat("{$fixtureDir}/small.txt"));
        $this->assertIsArray($stat);
        $this->assertStatSame(\stat("{$fixtureDir}/small.txt"), $stat);
    }

    public function testDirStat(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = (yield File\stat("{$fixtureDir}/dir"));
        $this->assertIsArray($stat);
        $this->assertStatSame(\stat("{$fixtureDir}/dir"), $stat);
    }

    public function testNonexistentPathStatResolvesToNull(): \Generator
    {
        $fixtureDir = Fixture::path();
        $stat = (yield File\stat("{$fixtureDir}/nonexistent"));
        $this->assertNull($stat);
    }

    public function testExists(): \Generator
    {
        $fixtureDir = Fixture::path();
        $this->assertFalse(yield File\exists("{$fixtureDir}/nonexistent"));
        $this->assertTrue(yield File\exists("{$fixtureDir}/small.txt"));
    }

    public function testGet(): \Generator
    {
        $fixtureDir = Fixture::path();
        $this->assertSame("small", yield File\get("{$fixtureDir}/small.txt"));
    }

    public function testSize(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/small.txt";
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

    public function testIsdirResolvesTrueOnDirectoryPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/dir";
        $this->assertTrue(yield File\isdir($path));
    }

    public function testIsdirResolvesFalseOnFilePath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/small.txt";
        $this->assertFalse(yield File\isdir($path));
    }

    public function testIsdirResolvesFalseOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        $this->assertFalse(yield File\isdir($path));
    }

    public function testIsfileResolvesTrueOnFilePath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/small.txt";
        $this->assertTrue(yield File\isfile($path));
    }

    public function testIsfileResolvesFalseOnDirectoryPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/dir";
        $this->assertFalse(yield File\isfile($path));
    }

    public function testIsfileResolvesFalseOnNonexistentPath(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        $this->assertFalse(yield File\isfile($path));
    }

    public function testRename(): \Generator
    {
        $fixtureDir = Fixture::path();

        $contents1 = "rename test";
        $old = "{$fixtureDir}/rename1.txt";
        $new = "{$fixtureDir}/rename2.txt";

        yield File\put($old, $contents1);
        yield File\rename($old, $new);
        $contents2 = (yield File\get($new));
        yield File\unlink($new);

        $this->assertSame($contents1, $contents2);
    }

    public function testUnlink(): \Generator
    {
        $fixtureDir = Fixture::path();
        $toUnlink = "{$fixtureDir}/unlink";
        yield File\put($toUnlink, "unlink me");
        yield File\unlink($toUnlink);
        $this->assertNull(yield File\stat($toUnlink));
    }

    public function testMkdirRmdir(): \Generator
    {
        $fixtureDir = Fixture::path();

        $dir = "{$fixtureDir}/newdir";

        \umask(0022);

        yield File\mkdir($dir);
        $stat = yield File\stat($dir);
        $this->assertSame('0755', $this->getPermissionsFromStat($stat));
        yield File\rmdir($dir);
        $this->assertNull(yield File\stat($dir));

        // test for 0, because previous array_filter made that not work
        $dir = "{$fixtureDir}/newdir/with/recursive/creation/0/1/2";

        yield File\mkdir($dir, 0764, true);
        $stat = yield File\stat($dir);
        $this->assertSame('0744', $this->getPermissionsFromStat($stat));
    }

    public function testMtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/small.txt";
        $stat = (yield File\stat($path));
        $statMtime = $stat["mtime"];
        File\StatCache::clear($path);
        $this->assertSame($statMtime, (yield File\mtime($path)));
    }

    public function testMtimeFailsOnNonexistentPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        yield File\mtime($path);
    }

    public function testAtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/small.txt";
        $stat = (yield File\stat($path));
        $statAtime = $stat["atime"];
        File\StatCache::clear($path);
        $this->assertSame($statAtime, (yield File\atime($path)));
    }

    public function testAtimeFailsOnNonexistentPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
        yield File\atime($path);
    }

    public function testCtime(): \Generator
    {
        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/small.txt";
        $stat = (yield File\stat($path));
        $statCtime = $stat["ctime"];
        File\StatCache::clear($path);
        $this->assertSame($statCtime, (yield File\ctime($path)));
    }

    public function testCtimeFailsOnNonexistentPath(): \Generator
    {
        $this->expectException(FilesystemException::class);

        $fixtureDir = Fixture::path();
        $path = "{$fixtureDir}/nonexistent";
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

        $oldStat = (yield File\stat($touch));
        yield File\touch($touch, \time() + 10, \time() + 20);
        File\StatCache::clear($touch);
        $newStat = (yield File\stat($touch));
        yield File\unlink($touch);

        $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
        $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
    }

    private function assertStatSame(array $expected, array $actual): void
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
     * @return string
     */
    private function getPermissionsFromStat(array $stat): string
    {
        return \substr(\decoct($stat["mode"]), 1);
    }

    public function testChmod(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/small.txt";
        $this->assertNotSame('0777', \substr(\sprintf('%o', \fileperms($original)), -4));
        $this->assertTrue(yield File\chmod($original, 0777));
        \clearstatcache();
        $this->assertSame('0777', \substr(\sprintf('%o', \fileperms($original)), -4));
    }

    public function testChown(): \Generator
    {
        $fixtureDir = Fixture::path();

        $original = "{$fixtureDir}/small.txt";
        $user = \fileowner($original);
        $this->assertTrue(yield File\chown($original, $user));
    }
}
