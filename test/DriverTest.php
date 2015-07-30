<?php

namespace Amp\Filesystem\Test;

use Amp\Filesystem\Driver;

abstract class DriverTest extends \PHPUnit_Framework_TestCase {
    private static $fixtureId;

    private static function getFixturePath() {
        if (empty(self::$fixtureId)) {
            self::$fixtureId = uniqid();
        }

        return \sys_get_temp_dir() . "/amp_fs_fixture/" . __CLASS__ . self::$fixtureId;
    }

    private static function clearFixtureDir() {
        $fixtureDir = self::getFixturePath();
        if (!file_exists($fixtureDir)) {
            return;
        }

        if (\stripos(\PHP_OS, "win") === 0) {
            \system('rd /Q /S "' . $fixtureDir . '"');
        } else {
            \system('/bin/rm -rf ' . escapeshellarg($fixtureDir));
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

    public function testScandir() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();
            $actual = (yield \Amp\Filesystem\scandir($fixtureDir));
            $expected = ["dir", "small.txt"];
            $this->assertSame($expected, $actual);
        });
    }

    public function testSymlink() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield \Amp\Filesystem\symlink($target, $link));
            $this->assertTrue(\is_link($link));
            yield \Amp\Filesystem\unlink($link);
        });
    }

    public function testLstat() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield \Amp\Filesystem\symlink($target, $link));
            $this->assertTrue(is_array(yield \Amp\Filesystem\lstat($link)));
            yield \Amp\Filesystem\unlink($link);
        });
    }

    public function testStatForFile() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $stat = (yield \Amp\Filesystem\stat("{$fixtureDir}/small.txt"));
            $this->assertInternalType("array", $stat);
            $this->assertTrue($stat["isfile"]);
            $this->assertFalse($stat["isdir"]);
        });
    }

    public function testStatForDirectory() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $stat = (yield \Amp\Filesystem\stat("{$fixtureDir}/dir"));
            $this->assertInternalType("array", $stat);
            $this->assertTrue($stat["isdir"]);
            $this->assertFalse($stat["isfile"]);
        });
    }

    public function testStatForNonexistentPath() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $stat = (yield \Amp\Filesystem\stat("{$fixtureDir}/nonexistent"));
            $this->assertNull($stat);
        });
    }

    public function testRename() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $contents1 = "rename test";
            $old = "{$fixtureDir}/rename1.txt";
            $new = "{$fixtureDir}/rename2.txt";

            yield \Amp\Filesystem\put($old, $contents1);
            yield \Amp\Filesystem\rename($old, $new);
            $contents2 = (yield \Amp\Filesystem\get($new));
            yield \Amp\Filesystem\unlink($new);

            $this->assertSame($contents1, $contents2);
        });
    }

    public function testUnlink() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $toUnlink = "{$fixtureDir}/unlink";

            yield \Amp\Filesystem\put($toUnlink, "unlink me");
            $this->assertTrue((bool) (yield \Amp\Filesystem\stat($toUnlink)));
            yield \Amp\Filesystem\unlink($toUnlink);
            $this->assertNull(yield \Amp\Filesystem\stat($toUnlink));
        });
    }

    public function testMkdirRmdir() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $dir = "{$fixtureDir}/newdir";

            yield \Amp\Filesystem\mkdir($dir);
            $stat = (yield \Amp\Filesystem\stat($dir));
            $this->assertTrue($stat["isdir"]);
            $this->assertFalse($stat["isfile"]);
            yield \Amp\Filesystem\rmdir($dir);
            $this->assertNull(yield \Amp\Filesystem\stat($dir));
        });
    }

    /**
     * @group slow
     */
    public function testTouch() {
        \Amp\run(function () {
            $fixtureDir = self::getFixturePath();

            $touch = "{$fixtureDir}/touch";
            yield \Amp\Filesystem\put($touch, "touch me");

            $oldStat = (yield \Amp\Filesystem\stat($touch));
            sleep(1);
            yield \Amp\Filesystem\touch($touch);
            $newStat = (yield \Amp\Filesystem\stat($touch));
            yield \Amp\Filesystem\unlink($touch);

            $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
            $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
        });
    }
}
