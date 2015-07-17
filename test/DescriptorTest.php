<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\Fs\Filesystem;

abstract class DescriptorTest extends \PHPUnit_Framework_TestCase {
    abstract protected function getReactor();
    abstract protected function getFilesystem(Reactor $reactor);

    public function testReadWriteCreate() {
        $this->getReactor()->run(function($reactor) {
            $path = __DIR__ . "/fixture/new.txt";
            $fs = $this->getFilesystem($reactor);
            $flags = Filesystem::READ | Filesystem::WRITE | Filesystem::CREATE;
            $fh = (yield $fs->open($path, $flags));
            yield $fh->write(0, "test");
            $data = (yield $fh->read(0, 8192));
            $this->assertSame("test", $data);
            yield $fh->close();
            yield $fs->unlink($path);
        });
    }

    /**
     * @expectedException RuntimeException
     */
    public function testWriteFailsOnDirectory() {
        $this->getReactor()->run(function($reactor) {
            $path = __DIR__ . "/fixture/dir";
            $fs = $this->getFilesystem($reactor);
            $flags = Filesystem::READ | Filesystem::WRITE | Filesystem::CREATE;
            $fh = (yield $fs->open($path, $flags));
            yield $fh->write(0, "should fail because this is a directory");
        });
    }

    /**
     * @expectedException RuntimeException
     */
    public function testReadFailsOnDirectory() {
        $this->getReactor()->run(function($reactor) {
            $path = __DIR__ . "/fixture/dir";
            $fs = $this->getFilesystem($reactor);
            $flags = Filesystem::READ | Filesystem::WRITE | Filesystem::CREATE;
            $fh = (yield $fs->open($path, $flags));
            yield $fh->read(0, 8192);
        });
    }

    public function testTruncate() {
        $this->getReactor()->run(function($reactor) {
            $path = __DIR__ . "/fixture/truncate.txt";
            $fs = $this->getFilesystem($reactor);
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

            // file
            $fh = (yield $fs->open(__DIR__ . "/fixture/small.txt"));
            $stat = (yield $fh->stat());
            $this->assertTrue($stat["isfile"]);
            $this->assertFalse($stat["isdir"]);

            // directory
            $fh = (yield $fs->open(__DIR__ . "/fixture/dir"));
            $stat = (yield $fh->stat());
            $this->assertFalse($stat["isfile"]);
            $this->assertTrue($stat["isdir"]);
        });
    }
}
