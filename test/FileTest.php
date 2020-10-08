<?php

namespace Amp\File\Test;

use Amp\ByteStream\ClosedException;
use Amp\File;

abstract class FileTest extends FilesystemTest
{
    private File\Driver $driver;

    public function testWrite()
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());

        $handle->write("foo");
        $handle->write("bar");
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertSame(6, $handle->tell());
        $this->assertTrue($handle->eof());
        $this->assertSame("foobar", $contents);

        $handle->close();
    }

    public function testEmptyWrite()
    {
        $path = Fixture::path() . "/write";

        $handle = $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());

        $handle->write("");
        $this->assertSame(0, $handle->tell());

        $handle->close();
    }

    public function testWriteAfterClose()
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");
        $handle->close();

        $this->expectException(ClosedException::class);
        $handle->write("bar");
    }

    public function testDoubleClose()
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = $this->driver->openFile($path, "c+");
        $handle->close();
        $this->assertNull($handle->close());
    }

    public function testWriteAfterEnd()
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());
        $handle->end("foo");

        $this->expectException(ClosedException::class);
        $handle->write("bar");
    }

    public function testWriteInAppendMode()
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "a+");
        $this->assertSame(0, $handle->tell());
        $handle->write("bar");
        $handle->write("foo");
        $handle->write("baz");
        $this->assertSame(9, $handle->tell());
        $handle->seek(0);
        $this->assertSame(0, $handle->tell());
        $this->assertSame("barfoobaz", $handle->read());
    }

    public function testReadingToEof()
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $contents = "";
        $position = 0;

        $stat = $this->driver->getStatus(__FILE__);
        $chunkSize = (int) \floor(($stat["size"] / 5));

        while (!$handle->eof()) {
            $chunk = $handle->read($chunkSize);
            $contents .= $chunk;
            $position += \strlen($chunk);
            $this->assertSame($position, $handle->tell());
        }

        $this->assertNull($handle->read());
        $this->assertSame($this->driver->read(__FILE__), $contents);

        $handle->close();
    }

    public function testSequentialReads()
    {
        $handle = $this->driver->openFile(__FILE__, "r");

        $contents = "";
        $contents .= $handle->read(10);
        $contents .= $handle->read(10);

        $expected = \substr($this->driver->read(__FILE__), 0, 20);
        $this->assertSame($expected, $contents);

        $handle->close();
    }

    public function testReadingFromOffset()
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        $handle->seek(10);
        $this->assertSame(10, $handle->tell());
        $chunk = $handle->read(90);
        $this->assertSame(100, $handle->tell());
        $expected = \substr($this->driver->read(__FILE__), 10, 90);
        $this->assertSame($expected, $chunk);

        $handle->close();
    }

    public function testSeekThrowsOnInvalidWhence()
    {
        $this->expectException(\Error::class);

        try {
            $handle = $this->driver->openFile(__FILE__, "r");
            $handle->seek(0, 99999);
        } finally {
            $handle->close();
        }
    }

    public function testSeekSetCur()
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        $handle->seek(10);
        $this->assertSame(10, $handle->tell());
        $handle->seek(-10, \SEEK_CUR);
        $this->assertSame(0, $handle->tell());
        $handle->close();
    }

    public function testSeekSetEnd()
    {
        $size = \filesize(__FILE__);
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        $handle->seek(-10, \SEEK_END);
        $this->assertSame($size - 10, $handle->tell());
        $handle->close();
    }

    public function testPath()
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(__FILE__, $handle->getPath());
        $handle->close();
    }

    public function testMode()
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame("r", $handle->getMode());
        $handle->close();
    }

    public function testClose()
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $handle->close();

        $this->expectException(ClosedException::class);
        $handle->read();
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToSmallerSize()
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");

        $handle->write("foo");
        $handle->write("bar");
        $handle->truncate(4);
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertTrue($handle->eof());
        $this->assertSame("foob", $contents);

        $handle->write("bar");
        $this->assertSame(7, $handle->tell());
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertSame("foobbar", $contents);

        $handle->close();
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToLargerSize()
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");

        $handle->write("foo");
        $handle->truncate(6);
        $this->assertSame(3, $handle->tell());
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertTrue($handle->eof());
        $this->assertSame("foo\0\0\0", $contents);

        $handle->write("bar");
        $this->assertSame(9, $handle->tell());
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertSame("foo\0\0\0bar", $contents);

        $handle->close();
    }

    abstract protected function createDriver(): File\Driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }
}
