<?php

namespace Amp\File\Test;

use Amp as amp;
use Amp\File as file;

abstract class HandleTest extends \PHPUnit_Framework_TestCase {
    public static function setUpBeforeClass() {
        Fixture::init();
    }

    public static function tearDownAfterClass() {
        Fixture::clear();
    }

    protected function setUp() {
        file\StatCache::clear();
    }

    public function testWrite() {
        amp\run(function () {
            $path = Fixture::path() . "/write";
            $handle = (yield file\open($path, "c+"));
            $this->assertSame(0, $handle->tell());

            yield $handle->write("foo");
            yield $handle->write("bar");
            $handle->seek(0);
            $contents = (yield $handle->read(8192));
            $this->assertSame(6, $handle->tell());
            $this->assertTrue($handle->eof());
            $this->assertSame("foobar", $contents);

            yield $handle->close();
            yield file\unlink($path);
        });
    }

    public function testReadingToEof() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            $contents = "";
            $position = 0;

            $stat = (yield file\stat(__FILE__));
            $chunkSize = \floor(($stat["size"] / 5));

            while (!$handle->eof()) {
                $chunk = (yield $handle->read($chunkSize));
                $contents .= $chunk;
                $position += \strlen($chunk);
                $this->assertSame($position, $handle->tell());
            }

            $this->assertSame((yield file\get(__FILE__)), $contents);
        });
    }

    public function testQueuedReads() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));

            $contents = "";
            $read1 = $handle->read(10);
            $handle->seek(10);
            $read2 = $handle->read(10);

            $contents .= (yield $read1);
            $contents .= (yield $read2);

            $expected = \substr((yield file\get(__FILE__)), 0, 20);
            $this->assertSame($expected, $contents);
        });
    }

    public function testReadingFromOffset() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            $this->assertSame(0, $handle->tell());
            $handle->seek(10);
            $this->assertSame(10, $handle->tell());
            $chunk = (yield $handle->read(90));
            $this->assertSame(100, $handle->tell());
            $expected = \substr((yield file\get(__FILE__)), 10, 90);
            $this->assertSame($expected, $chunk);
        });
    }

    /**
     * @expectedException Amp\File\FilesystemException
     * @expectedExceptionMessage Invalid whence parameter; SEEK_SET, SEEK_CUR or SEEK_END expected
     */
    public function testSeekThrowsOnInvalidWhence() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            $handle->seek(0, 99999);
        });
    }

    public function testSeekSetCur() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            $this->assertSame(0, $handle->tell());
            $handle->seek(10);
            $this->assertSame(10, $handle->tell());
            $handle->seek(-10, \SEEK_CUR);
            $this->assertSame(0, $handle->tell());
        });
    }

    public function testSeekSetEnd() {
        amp\run(function () {
            $size = (yield file\size(__FILE__));
            $handle = (yield file\open(__FILE__, "r"));
            $this->assertSame(0, $handle->tell());
            $handle->seek(-10, \SEEK_END);
            $this->assertSame($size - 10, $handle->tell());
        });
    }

    public function testPath() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            $this->assertSame(__FILE__, $handle->path());
        });
    }

    public function testMode() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            $this->assertSame("r", $handle->mode());
        });
    }

    public function testClose() {
        amp\run(function () {
            $handle = (yield file\open(__FILE__, "r"));
            yield $handle->close();
        });
    }
}
