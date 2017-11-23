<?php

namespace Amp\File\Test;

use Amp\File as file;
use Amp\PHPUnit\TestCase;

abstract class DriverTest extends TestCase {
    protected function setUp() {
        Fixture::init();
        File\StatCache::clear();
    }

    protected function tearDown() {
        Fixture::clear();
    }

    abstract protected function execute(callable $cb);

    public function testScandir() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $actual = yield File\scandir($fixtureDir);
            $expected = ["dir", "small.txt"];
            $this->assertSame($expected, $actual);
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testScandirThrowsIfPathNotADirectory() {
        $this->execute(function () {
            (yield File\scandir(__FILE__));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testScandirThrowsIfPathDoesntExist() {
        $this->execute(function () {
            $path = Fixture::path() . "/nonexistent";
            (yield File\scandir($path));
        });
    }

    public function testSymlink() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();

            $original = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield File\symlink($original, $link));
            $this->assertTrue(\is_link($link));
            yield File\unlink($link);
        });
    }

    public function testLstat() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield File\symlink($target, $link));
            $this->assertInternalType('array', yield File\lstat($link));
            yield File\unlink($link);
        });
    }

    public function testFileStat() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $stat = (yield File\stat("{$fixtureDir}/small.txt"));
            $this->assertInternalType("array", $stat);
        });
    }

    public function testDirStat() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $stat = (yield File\stat("{$fixtureDir}/dir"));
            $this->assertInternalType("array", $stat);
        });
    }

    public function testNonexistentPathStatResolvesToNull() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $stat = (yield File\stat("{$fixtureDir}/nonexistent"));
            $this->assertNull($stat);
        });
    }

    public function testExists() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $this->assertFalse(yield File\exists("{$fixtureDir}/nonexistent"));
            $this->assertTrue(yield File\exists("{$fixtureDir}/small.txt"));
        });
    }

    public function testGet() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $this->assertSame("small", yield File\get("{$fixtureDir}/small.txt"));
        });
    }

    public function testSize() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield File\stat($path));
            $size = $stat["size"];
            File\StatCache::clear($path);
            $this->assertSame($size, (yield File\size($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testSizeFailsOnNonexistentPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/nonexistent";
            yield File\size($path);
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testSizeFailsOnDirectoryPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/dir";
            $this->assertTrue(yield File\isdir($path));
            File\StatCache::clear($path);
            yield File\size($path);
        });
    }

    public function testIsdirResolvesTrueOnDirectoryPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/dir";
            $this->assertTrue(yield File\isdir($path));
        });
    }

    public function testIsdirResolvesFalseOnFilePath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/small.txt";
            $this->assertFalse(yield File\isdir($path));
        });
    }

    public function testIsdirResolvesFalseOnNonexistentPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/nonexistent";
            $this->assertFalse(yield File\isdir($path));
        });
    }

    public function testIsfileResolvesTrueOnFilePath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/small.txt";
            $this->assertTrue(yield File\isfile($path));
        });
    }

    public function testIsfileResolvesFalseOnDirectoryPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/dir";
            $this->assertFalse(yield File\isfile($path));
        });
    }

    public function testIsfileResolvesFalseOnNonexistentPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/nonexistent";
            $this->assertFalse(yield File\isfile($path));
        });
    }

    public function testRename() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();

            $contents1 = "rename test";
            $old = "{$fixtureDir}/rename1.txt";
            $new = "{$fixtureDir}/rename2.txt";

            yield File\put($old, $contents1);
            yield File\rename($old, $new);
            $contents2 = (yield File\get($new));
            yield File\unlink($new);

            $this->assertSame($contents1, $contents2);
        });
    }

    public function testUnlink() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $toUnlink = "{$fixtureDir}/unlink";
            yield File\put($toUnlink, "unlink me");
            yield File\unlink($toUnlink);
            $this->assertNull(yield File\stat($toUnlink));
        });
    }

    public function testMkdirRmdir() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();

            $dir = "{$fixtureDir}/newdir";

            yield File\mkdir($dir);
            $stat = yield File\stat($dir);
            $this->assertSame(0644, $stat["mode"] & 0777);
            yield File\rmdir($dir);
            $this->assertNull(yield File\stat($dir));

            // test for 0, because previous array_filter made that not work
            $dir = "{$fixtureDir}/newdir/with/recursive/creation/0/1/2";

            yield File\mkdir($dir, 0764, true);
            $stat = yield File\stat($dir);
            $this->assertSame(0764 & (~\umask()), $stat["mode"] & 0777);
        });
    }

    public function testMtime() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield File\stat($path));
            $statMtime = $stat["mtime"];
            File\StatCache::clear($path);
            $this->assertSame($statMtime, (yield File\mtime($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testMtimeFailsOnNonexistentPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/nonexistent";
            yield File\mtime($path);
        });
    }

    public function testAtime() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield File\stat($path));
            $statAtime = $stat["atime"];
            File\StatCache::clear($path);
            $this->assertSame($statAtime, (yield File\atime($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testAtimeFailsOnNonexistentPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/nonexistent";
            yield File\atime($path);
        });
    }

    public function testCtime() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield File\stat($path));
            $statCtime = $stat["ctime"];
            File\StatCache::clear($path);
            $this->assertSame($statCtime, (yield File\ctime($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testCtimeFailsOnNonexistentPath() {
        $this->execute(function () {
            $fixtureDir = Fixture::path();
            $path = "{$fixtureDir}/nonexistent";
            yield File\ctime($path);
        });
    }

    /**
     * @group slow
     */
    public function testTouch() {
        $this->execute(function () {
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
        });
    }
}
