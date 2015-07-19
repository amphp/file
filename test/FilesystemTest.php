<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\Fs\Filesystem;

abstract class FilesystemTest extends \PHPUnit_Framework_TestCase {
    abstract protected function getReactor();
    abstract protected function getFilesystem(Reactor $reactor);

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

        if (stripos(\PHP_OS, "win") === 0) {
            system('rd /Q /S "' . $fixtureDir . '"');
        } else {
            system('/bin/rm -rf ' . escapeshellarg($fixtureDir));
        }
    }

    public static function setUpBeforeClass() {
        $fixtureDir = self::getFixturePath();

        self::clearFixtureDir();

        if (!mkdir($fixtureDir, $mode = 0777, $recursive = true)) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture directory: {$fixtureDir}"
            );
        }
        if (!mkdir($fixtureDir . "/dir", $mode = 0777, $recursive = true)) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture directory"
            );
        }
        if (!file_put_contents($fixtureDir . "/small.txt", "small")) {
            throw new \RuntimeException(
                "Failed creating temporary test fixture file"
            );
        }
    }

    public static function tearDownAfterClass() {
        self::clearFixtureDir();
    }

    public function testOpen() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $descriptor = (yield $fs->open("{$fixtureDir}/small.txt", Filesystem::READ));
            $this->assertInstanceOf("Amp\Fs\Descriptor", $descriptor);
        });
    }

    public function testScandir() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();
            $actual = (yield $fs->scandir($fixtureDir));
            $expected = ["dir", "small.txt"];
            $this->assertSame($expected, $actual);
        });
    }

    public function testSymlink() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield $fs->symlink($target, $link));
            $this->assertTrue(is_link($link));
            yield $fs->unlink($link);
        });
    }

    public function testLstat() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $target = "{$fixtureDir}/small.txt";
            $link = "{$fixtureDir}/symlink.txt";
            $this->assertTrue(yield $fs->symlink($target, $link));
            $this->assertTrue(is_array(yield $fs->lstat($link)));
            yield $fs->unlink($link);
        });
    }

    /**
     * @expectedException RuntimeException
     */
    public function testOpenFailsOnNonexistentFile() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $descriptor = (yield $fs->open("{$fixtureDir}/nonexistent", Filesystem::READ));
            $this->assertInstanceOf("Amp\Fs\Descriptor", $descriptor);
        });
    }

    public function testStatForFile() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $stat = (yield $fs->stat("{$fixtureDir}/small.txt"));
            $this->assertInternalType("array", $stat);
            $this->assertTrue($stat["isfile"]);
            $this->assertFalse($stat["isdir"]);
        });
    }

    public function testStatForDirectory() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $stat = (yield $fs->stat("{$fixtureDir}/dir"));
            $this->assertInternalType("array", $stat);
            $this->assertTrue($stat["isdir"]);
            $this->assertFalse($stat["isfile"]);
        });
    }

    public function testStatForNonexistentPath() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $stat = (yield $fs->stat("{$fixtureDir}/nonexistent"));
            $this->assertNull($stat);
        });
    }

    public function testRename() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $contents1 = "rename test";
            $old = "{$fixtureDir}/rename1.txt";
            $new = "{$fixtureDir}/rename2.txt";

            yield $fs->put($old, $contents1);
            yield $fs->rename($old, $new);
            $contents2 = (yield $fs->get($new));
            yield $fs->unlink($new);

            $this->assertSame($contents1, $contents2);
        });
    }

    public function testUnlink() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $toUnlink = "{$fixtureDir}/unlink";

            yield $fs->put($toUnlink, "unlink me");
            $this->assertTrue((bool) (yield $fs->stat($toUnlink)));
            yield $fs->unlink($toUnlink);
            $this->assertNull(yield $fs->stat($toUnlink));
        });
    }

    public function testMkdirRmdir() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $dir = "{$fixtureDir}/newdir";

            yield $fs->mkdir($dir);
            $stat = (yield $fs->stat($dir));
            $this->assertTrue($stat["isdir"]);
            $this->assertFalse($stat["isfile"]);
            yield $fs->rmdir($dir);
            $this->assertNull(yield $fs->stat($dir));
        });
    }

    /**
     * @group slow
     */
    public function testTouch() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = self::getFixturePath();

            $touch = "{$fixtureDir}/touch";
            yield $fs->put($touch, "touch me");

            $oldStat = (yield $fs->stat($touch));
            sleep(1);
            yield $fs->touch($touch);
            $newStat = (yield $fs->stat($touch));
            yield $fs->unlink($touch);

            $this->assertTrue($newStat["atime"] > $oldStat["atime"]);
            $this->assertTrue($newStat["mtime"] > $oldStat["mtime"]);
        });
    }
}
