<?php

namespace Amp\File\Test;

use Amp\ByteStream\ClosedException;
use Amp\File;

abstract class FileTest extends FilesystemTest
{
    protected File\Driver $driver;

    public function testWrite(): void
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());

        $handle->write("foo");
        $handle->write("bar");
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertSame(6, $handle->tell());
        $this->assertTrue($handle->atEnd());
        $this->assertSame("foobar", $contents);

        $handle->close();
    }

    public function testEmptyWrite(): void
    {
        $path = Fixture::path() . "/write";

        $handle = $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());

        $handle->write("");
        $this->assertSame(0, $handle->tell());

        $handle->close();
    }

    public function testWriteAfterClose(): void
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");
        $handle->close();

        $this->expectException(ClosedException::class);
        $handle->write("bar");
    }

    public function testDoubleClose(): void
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = $this->driver->openFile($path, "c+");
        $handle->close();
        $handle->close();

        $this->expectNotToPerformAssertions();
    }

    public function testWriteAfterEnd(): void
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());
        $handle->end("foo");

        $this->expectException(ClosedException::class);
        $handle->write("bar");
    }

    public function testWriteInAppendMode(): void
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

    public function testReadingToEnd(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $contents = "";
        $position = 0;

        $stat = $this->driver->getStatus(__FILE__);
        $chunkSize = (int) \floor(($stat["size"] / 5));

        while (!$handle->atEnd()) {
            $chunk = $handle->read(length: $chunkSize);
            $contents .= $chunk;
            $position += \strlen($chunk ?? '');
            $this->assertSame($position, $handle->tell());
        }

        $this->assertNull($handle->read());
        $this->assertSame($this->driver->read(__FILE__), $contents);

        $handle->close();
    }

    public function testSequentialReads(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");

        $contents = "";
        $contents .= $handle->read(length: 10);
        $contents .= $handle->read(length: 10);

        $expected = \substr($this->driver->read(__FILE__), 0, 20);
        $this->assertSame($expected, $contents);

        $handle->close();
    }

    public function testReadingFromOffset(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        $handle->seek(10);
        $this->assertSame(10, $handle->tell());
        $chunk = $handle->read(length: 90);
        $this->assertSame(100, $handle->tell());
        $expected = \substr($this->driver->read(__FILE__), 10, 90);
        $this->assertSame($expected, $chunk);

        $handle->close();
    }

    public function testSeekThrowsOnInvalidWhence(): void
    {
        $this->expectException(\Error::class);

        $handle = $this->driver->openFile(__FILE__, "r");

        try {
            $handle->seek(0, 99999);
        } finally {
            $handle->close();
        }
    }

    public function testSeekSetCur(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        $handle->seek(10);
        $this->assertSame(10, $handle->tell());
        $handle->seek(-10, \SEEK_CUR);
        $this->assertSame(0, $handle->tell());
        $handle->close();
    }

    public function testSeekSetEnd(): void
    {
        $size = \filesize(__FILE__);
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        $handle->seek(-10, \SEEK_END);
        $this->assertSame($size - 10, $handle->tell());
        $handle->close();
    }

    public function testPath(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame(__FILE__, $handle->getPath());
        $handle->close();
    }

    public function testMode(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $this->assertSame("r", $handle->getMode());
        $handle->close();
    }

    public function testClose(): void
    {
        $handle = $this->driver->openFile(__FILE__, "r");
        $handle->close();

        $this->expectException(ClosedException::class);
        $handle->read();
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToSmallerSize(): void
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");

        $handle->write("foo");
        $handle->write("bar");
        $handle->truncate(4);
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertTrue($handle->atEnd());
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
    public function testTruncateToLargerSize(): void
    {
        $path = Fixture::path() . "/write";
        $handle = $this->driver->openFile($path, "c+");

        $handle->write("foo");
        $handle->truncate(6);
        $this->assertSame(3, $handle->tell());
        $handle->seek(0);
        $contents = $handle->read();
        $this->assertTrue($handle->atEnd());
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
