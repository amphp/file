<?php

namespace Amp\File\Test;

use Amp as amp;
use Amp\File as file;

abstract class DriverTest extends \PHPUnit_Framework_TestCase {
    private static $fixtureId;

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
    }

    protected function setUp() {
        file\StatCache::clear();
    }

    protected function timeoutTest($name, callable $func) {
        $co = amp\coroutine($func);
        return function () use ($name, $func) {
            $co = amp\coroutine($func);
            $promise = $co();
            yield amp\timeout($promise, 3000);
            echo "{$name} completed successfully\n";
        };
    }

    public function testScandir() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $actual = (yield file\scandir($fixtureDir));
            $expected = ["dir", "small.txt"];
            $this->assertSame($expected, $actual);
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testScandirThrowsIfPathNotADirectory() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            (yield file\scandir(__FILE__));
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testScandirThrowsIfPathDoesntExist() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $path = self::getFixturePath() . "/nonexistent";
            (yield file\scandir($path));
        }));
    }

    public function testSymlink() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();

            $original = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield file\symlink($original, $link));
            $this->assertTrue(\is_link($link));
            yield file\unlink($link);
        }));
    }

    public function testLstat() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield file\symlink($target, $link));
            $this->assertTrue(is_array(yield file\lstat($link)));
            yield file\unlink($link);
        }));
    }

    public function testFileStat() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $stat = (yield file\stat("{$fixtureDir}/small.txt"));
            $this->assertInternalType("array", $stat);
        }));
    }

    public function testDirStat() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $stat = (yield file\stat("{$fixtureDir}/dir"));
            $this->assertInternalType("array", $stat);
        }));
    }

    public function testNonexistentPathStatResolvesToNull() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $stat = (yield file\stat("{$fixtureDir}/nonexistent"));
            $this->assertNull($stat);
        }));
    }

    public function testExists() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $this->assertFalse(yield file\exists("{$fixtureDir}/nonexistent"));
            $this->assertTrue(yield file\exists("{$fixtureDir}/small.txt"));
        }));
    }

    public function testSize() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $size = $stat["size"];
            file\StatCache::clear($path);
            $this->assertSame($size, (yield file\size($path)));
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testSizeFailsOnNonexistentPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\size($path);
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testSizeFailsOnDirectoryPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/dir";
            $this->assertTrue(yield file\isdir($path));
            file\StatCache::clear($path);
            yield file\size($path);
        }));
    }

    public function testIsdirResolvesTrueOnDirectoryPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/dir";
            $this->assertTrue(yield file\isdir($path));
        }));
    }

    public function testIsdirResolvesFalseOnFilePath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $this->assertFalse(yield file\isdir($path));
        }));
    }

    public function testIsdirResolvesFalseOnNonexistentPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            $this->assertFalse(yield file\isdir($path));
        }));
    }

    public function testIsfileResolvesTrueOnFilePath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $this->assertTrue(yield file\isfile($path));
        }));
    }

    public function testIsfileResolvesFalseOnDirectoryPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/dir";
            $this->assertFalse(yield file\isfile($path));
        }));
    }

    public function testIsfileResolvesFalseOnNonexistentPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            $this->assertFalse(yield file\isfile($path));
        }));
    }

    public function testRename() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();

            $contents1 = "rename test";
            $old = "{$fixtureDir}/rename1.txt";
            $new = "{$fixtureDir}/rename2.txt";

            yield file\put($old, $contents1);
            yield file\rename($old, $new);
            $contents2 = (yield file\get($new));
            yield file\unlink($new);

            $this->assertSame($contents1, $contents2);
        }));
    }

    public function testUnlink() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $toUnlink = "{$fixtureDir}/unlink";
            yield file\put($toUnlink, "unlink me");
            $this->assertTrue((bool) (yield file\stat($toUnlink)));
            yield file\unlink($toUnlink);
            $this->assertNull(yield file\stat($toUnlink));
        }));
    }

    public function testMkdirRmdir() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();

            $dir = "{$fixtureDir}/newdir";

            yield file\mkdir($dir);
            $stat = (yield file\stat($dir));
            yield file\rmdir($dir);
            $this->assertNull(yield file\stat($dir));
        }));
    }

    public function testMtime() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $statMtime = $stat["mtime"];
            file\StatCache::clear($path);
            $this->assertSame($statMtime, (yield file\mtime($path)));
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testMtimeFailsOnNonexistentPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\mtime($path);
        }));
    }

    public function testAtime() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $statAtime = $stat["atime"];
            file\StatCache::clear($path);
            $this->assertSame($statAtime, (yield file\atime($path)));
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testAtimeFailsOnNonexistentPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\atime($path);
        }));
    }

    public function testCtime() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/small.txt";
            $stat = (yield file\stat($path));
            $statCtime = $stat["ctime"];
            file\StatCache::clear($path);
            $this->assertSame($statCtime, (yield file\ctime($path)));
        }));
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testCtimeFailsOnNonexistentPath() {
        amp\run($this->timeoutTest(__METHOD__, function () {
            $fixtureDir = self::getFixturePath();
            $path = "{$fixtureDir}/nonexistent";
            yield file\ctime($path);
        }));
    }

    /**
     * @group slow
     */
    public function testTouch() {
        amp\run($this->timeoutTest(__METHOD__, function () {
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
        }));
    }
}
