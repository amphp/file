<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\Fs\Filesystem;

abstract class DescriptorTest extends \PHPUnit_Framework_TestCase {
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
    
    public function testReadWriteCreate() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = $this->getFixturePath();

            $path = "{$fixtureDir}/new.txt";
            $flags = Filesystem::READ | Filesystem::WRITE | Filesystem::CREATE;
            $fh = (yield $fs->open($path, $flags));
            yield $fh->write(0, "test");
            $data = (yield $fh->read(0, 8192));
            $this->assertSame("test", $data);
            yield $fh->close();
            yield $fs->unlink($path);
        });
    }

    public function testTruncate() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = $this->getFixturePath();

            $path = "{$fixtureDir}/truncate.txt";
            $flags = Filesystem::READ | Filesystem::WRITE | Filesystem::CREATE;
            $fh = (yield $fs->open($path, $flags));
            yield $fh->write(0, "test");
            $data = (yield $fh->read(0, 8192));
            $this->assertSame("test", $data);
            yield $fh->truncate();
            yield $fh->close();

            $stat = (yield $fs->stat($path));
            $this->assertEquals(0, $stat["size"]);
            yield $fs->unlink($path);
        });
    }

    public function testStat() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $fixtureDir = $this->getFixturePath();

            $fh = (yield $fs->open("{$fixtureDir}/small.txt"));
            $stat = (yield $fh->stat());
            $this->assertInternalType("array", $stat);
        });
    }
}
