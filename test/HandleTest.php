<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\PHPUnit\TestCase;

abstract class HandleTest extends TestCase {
    protected function setUp() {
        Fixture::init();
        File\StatCache::clear();
    }

    protected function tearDown() {
        Fixture::clear();
    }

    abstract protected function execute(callable $cb);

    public function testWrite() {
        $this->execute(function () {
            $path = Fixture::path() . "/write";
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open($path, "c+");
            $this->assertSame(0, $handle->tell());

            $handle->write("foo");
            yield $handle->write("bar");
            yield $handle->seek(0);
            $contents = yield $handle->read();
            $this->assertSame(6, $handle->tell());
            $this->assertTrue($handle->eof());
            $this->assertSame("foobar", $contents);

            yield $handle->close();
        });
    }

    public function testReadingToEof() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $contents = "";
            $position = 0;

            $stat = yield File\stat(__FILE__);
            $chunkSize = (int) \floor(($stat["size"] / 5));

            while (!$handle->eof()) {
                $chunk = yield $handle->read($chunkSize);
                $contents .= $chunk;
                $position += \strlen($chunk);
                $this->assertSame($position, $handle->tell());
            }

            $this->assertNull(yield $handle->read());
            $this->assertSame(yield File\get(__FILE__), $contents);

            yield $handle->close();
        });
    }

    public function testSequentialReads() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $contents = "";
            $contents .= yield $handle->read(10);
            $contents .= yield $handle->read(10);

            $expected = \substr(yield File\get(__FILE__), 0, 20);
            $this->assertSame($expected, $contents);

            yield $handle->close();
        });
    }

    public function testReadingFromOffset() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(0, $handle->tell());
            yield $handle->seek(10);
            $this->assertSame(10, $handle->tell());
            $chunk = yield $handle->read(90);
            $this->assertSame(100, $handle->tell());
            $expected = \substr(yield File\get(__FILE__), 10, 90);
            $this->assertSame($expected, $chunk);

            yield $handle->close();
        });
    }

    /**
     * @expectedException \Error
     */
    public function testSeekThrowsOnInvalidWhence() {
        $this->execute(function () {
            try {
                /** @var \Amp\File\Handle $handle */
                $handle = yield File\open(__FILE__, "r");
                yield $handle->seek(0, 99999);
            } finally {
                yield $handle->close();
            }
        });
    }

    public function testSeekSetCur() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(0, $handle->tell());
            yield $handle->seek(10);
            $this->assertSame(10, $handle->tell());
            yield $handle->seek(-10, \SEEK_CUR);
            $this->assertSame(0, $handle->tell());
            yield $handle->close();
        });
    }

    public function testSeekSetEnd() {
        $this->execute(function () {
            $size = yield File\size(__FILE__);
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(0, $handle->tell());
            yield $handle->seek(-10, \SEEK_END);
            $this->assertSame($size - 10, $handle->tell());
            yield $handle->close();
        });
    }

    public function testPath() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame(__FILE__, $handle->path());
            yield $handle->close();
        });
    }

    public function testMode() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            $this->assertSame("r", $handle->mode());
            yield $handle->close();
        });
    }

    /**
     * @expectedException \Amp\File\FilesystemException
     */
    public function testClose() {
        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");
            yield $handle->close();
            yield $handle->read();
        });
    }
}
