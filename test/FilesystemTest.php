<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\Fs\Filesystem;

abstract class FilesystemTest extends \PHPUnit_Framework_TestCase {
    abstract protected function getReactor();
    abstract protected function getFilesystem(Reactor $reactor);

    public function testOpen() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $descriptor = (yield $fs->open(__DIR__ . "/fixture/small.txt", Filesystem::READ));
            $this->assertInstanceOf("Amp\Fs\Descriptor", $descriptor);
        });
    }

    public function testScandir() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);
            $actual = (yield $fs->scandir(__DIR__ . "/fixture"));
            $expected = ["dir", "small.txt"];
            $this->assertSame($expected, $actual);
        });
    }

    public function testSymlink() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);

            $target = __DIR__ . "/fixture/small.txt";
            $link = __DIR__ . "/fixture/symlink.txt";
            $this->assertTrue(yield $fs->symlink($target, $link));
            $this->assertTrue(is_link($link));
            yield $fs->unlink($link);
        });
    }

    public function testLstat() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);

            $target = __DIR__ . "/fixture/small.txt";
            $link = __DIR__ . "/fixture/symlink.txt";
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
            $descriptor = (yield $fs->open(__DIR__ . "/fixture/nonexistent", Filesystem::READ));
            $this->assertInstanceOf("Amp\Fs\Descriptor", $descriptor);
        });
    }

    public function testStat() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);

            // file
            $stat = (yield $fs->stat(__DIR__ . "/fixture/small.txt"));
            $this->assertTrue($stat["isfile"]);
            $this->assertFalse($stat["isdir"]);

            // directory
            $stat = (yield $fs->stat(__DIR__ . "/fixture/dir"));
            $this->assertFalse($stat["isfile"]);
            $this->assertTrue($stat["isdir"]);

            // nonexistent
            $stat = (yield $fs->stat(__DIR__ . "/fixture/nonexistent"));
            $this->assertNull($stat);
        });
    }

    public function testRename() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);

            $contents1 = "rename test";
            $old = __DIR__ . "/fixture/rename1.txt";
            $new = __DIR__ . "/fixture/rename2.txt";

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

            $toUnlink = __DIR__ . "/fixture/unlink";

            yield $fs->put($toUnlink, "unlink me");
            $this->assertTrue((bool) (yield $fs->stat($toUnlink)));
            yield $fs->unlink($toUnlink);
            $this->assertNull(yield $fs->stat($toUnlink));
        });
    }

    public function testMkdirRmdir() {
        $this->getReactor()->run(function($reactor) {
            $fs = $this->getFilesystem($reactor);

            $dir = __DIR__ . "/fixture/newdir";

            yield $fs->mkdir($dir);
            $stat = (yield $fs->stat($dir));
            $this->assertTrue($stat["isdir"]);
            $this->assertFalse($stat["isfile"]);
            yield $fs->rmdir($dir);
            $this->assertNull(yield $fs->stat($dir));
        });
    }
}
