<?php

namespace Amp\File\Test;

use Amp\File as file;

abstract class DriverTest extends \PHPUnit_Framework_TestCase {
    private static $fixtureId;
    private static $umask;

    private static function getFixturePath() {
        if (empty(self::$fixtureId)) {
            self::$fixtureId = \uniqid();
        }

        return \sys_get_temp_dir() . "/amphp_file_fixture/" . __CLASS__ . self::$fixtureId;
    }

    private static function clearFixtureDir() {
        $fixtureDir = self::getFixturePath();
        if (!file_exists($fixtureDir)) {
            return;
        }

        if (\stripos(\PHP_OS, "win") === 0) {
            \system('rd /Q /S "' . $fixtureDir . '"');
        } else {
            \system('/bin/rm -rf ' . \escapeshellarg($fixtureDir));
        }
    }

    public static function setUpBeforeClass() {
        $fixtureDir = self::getFixturePath();

        self::clearFixtureDir();
        self::$umask = umask(0022);

        if (!\mkdir($fixtureDir, $mode = 0777, $recursive = true)) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture directory: {$fixtureDir}"
            );
        }
        if (!\mkdir($fixtureDir . "/dir", $mode = 0777, $recursive = true)) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture directory"
            );
        }
        if (!\file_put_contents($fixtureDir . "/small.txt", "small")) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture file"
            );
        }
    }

    public static function tearDownAfterClass() {
        self::clearFixtureDir();
        umask(self::$umask);
    }

    protected function setUp() {
        file\StatCache::clear();
    }

    abstract protected function lRun(callable $cb);

    public function testScandir() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $actual = (yield file\scandir($fixtureDir));
            $expected = ["dir", "small.txt"];
            $this->assertSame($expected, $actual);
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testScandirThrowsIfPathNotADirectory() {
        $this->lRun(function () {
            (yield file\scandir(__FILE__));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testScandirThrowsIfPathDoesntExist() {
        $this->lRun(function () {
            $path = self::getFixturePath() . "/nonexistent";
            (yield file\scandir($path));
        });
    }

    public function testSymlink() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();

            $original = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield file\symlink($original, $link));
            $this->assertTrue(\is_link($link));
            yield file\unlink($link);
        });
    }

    public function testLstat() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield file\symlink($target, $link));
            $this->assertTrue(is_array(yield file\lstat($link)));
            yield file\unlink($link);
        });
    }

    public function testFileStat() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $stat = (yield file\stat("{$fixtureDir}/small.txt"));
            $this->assertInternalType("array", $stat);
        });
    }

    public function testDirStat() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $stat = (yield file\stat("{$fixtureDir}/dir"));
            $this->assertInternalType("array", $stat);
        });
    }

    public function testNonexistentPathStatResolvesToNull() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $stat = (yield file\stat("{$fixtureDir}/nonexistent"));
            $this->assertNull($stat);
        });
    }

    public function testExists() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $this->assertFalse(yield file\exists("{$fixtureDir}/nonexistent"));
            $this->assertTrue(yield file\exists("{$fixtureDir}/small.txt"));
        });
    }

    public function testSize() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $size = $stat["size"];
            file\StatCache::clear($path);
            $this->assertSame($size, (yield file\size($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testSizeFailsOnNonexistentPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\size($path);
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testSizeFailsOnDirectoryPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/dir";
            $this->assertTrue(yield file\isdir($path));
            file\StatCache::clear($path);
            yield file\size($path);
        });
    }

    public function testIsdirResolvesTrueOnDirectoryPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/dir";
            $this->assertTrue(yield file\isdir($path));
        });
    }

    public function testIsdirResolvesFalseOnFilePath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $this->assertFalse(yield file\isdir($path));
        });
    }

    public function testIsdirResolvesFalseOnNonexistentPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            $this->assertFalse(yield file\isdir($path));
        });
    }

    public function testIsfileResolvesTrueOnFilePath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $this->assertTrue(yield file\isfile($path));
        });
    }

    public function testIsfileResolvesFalseOnDirectoryPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/dir";
            $this->assertFalse(yield file\isfile($path));
        });
    }

    public function testIsfileResolvesFalseOnNonexistentPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            $this->assertFalse(yield file\isfile($path));
        });
    }

    public function testRename() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();

            $contents1 = "rename test";
            $old = "{$fixtureDir}/rename1.txt";
            $new = "{$fixtureDir}/rename2.txt";

            yield file\put($old, $contents1);
            yield file\rename($old, $new);
            $contents2 = (yield file\get($new));
            yield file\unlink($new);

            $this->assertSame($contents1, $contents2);
        });
    }

    public function testUnlink() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $toUnlink = "{$fixtureDir}/unlink";
            yield file\put($toUnlink, "unlink me");
            yield file\unlink($toUnlink);
            $this->assertNull(yield file\stat($toUnlink));
        });
    }

    public function testMkdirRmdir() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();

            $dir = "{$fixtureDir}/newdir";

            yield file\mkdir($dir);
            $stat = yield file\stat($dir);
            $this->assertTrue(($stat["mode"] & 0777) === 0644);
            yield file\rmdir($dir);
            $this->assertNull(yield file\stat($dir));

            $dir = "{$fixtureDir}/newdir/with/recursive/creation";

            yield file\mkdir($dir, 0764, true); // the umask is 022 by default
            $stat = yield file\stat($dir);
            $this->assertTrue(($stat["mode"] & 0777) == 0744);
        });
    }

    public function testMtime() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $statMtime = $stat["mtime"];
            file\StatCache::clear($path);
            $this->assertSame($statMtime, (yield file\mtime($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testMtimeFailsOnNonexistentPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\mtime($path);
        });
    }

    public function testAtime() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $statAtime = $stat["atime"];
            file\StatCache::clear($path);
            $this->assertSame($statAtime, (yield file\atime($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testAtimeFailsOnNonexistentPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\atime($path);
        });
    }

    public function testCtime() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $statCtime = $stat["ctime"];
            file\StatCache::clear($path);
            $this->assertSame($statCtime, (yield file\ctime($path)));
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testCtimeFailsOnNonexistentPath() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\ctime($path);
        });
    }

    /**
     * @group slow
     */
    public function testTouch() {
        $this->lRun(function () {
            $fixtureDir = self::getFixturePath();

            $touch = "{$fixtureDir}/touch";
            yield file\put($touch, "touch me");

            $oldStat = (yield file\stat($touch));
            sleep(1);
            yield file\touch($touch);
            file\StatCache::clear($touch);
            $newStat = (yield file\stat($touch));
            yield file\unlink($touch);

            $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
            $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
        });
    }
}
