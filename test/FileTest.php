<?php

namespace Amp\File\Test;

use Amp\ByteStream\ClosedException;
use Amp\File;

abstract class FileTest extends FilesystemTest
{
    /** @var File\Driver */
    private $driver;

    public function testWrite(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());

        $handle->write("foo");
        yield $handle->write("bar");
        yield $handle->seek(0);
        $contents = yield $handle->read();
        $this->assertSame(6, $handle->tell());
        $this->assertTrue($handle->eof());
        $this->assertSame("foobar", $contents);

        yield $handle->close();
    }

    public function testEmptyWrite(): \Generator
    {
        $path = Fixture::path() . "/write";

        $handle = yield $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());

        yield $handle->write("");
        $this->assertSame(0, $handle->tell());

        yield $handle->close();
    }

    public function testWriteAfterClose(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "c+");
        yield $handle->close();

        $this->expectException(ClosedException::class);
        yield $handle->write("bar");
    }

    public function testDoubleClose(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "c+");
        yield $handle->close();
        $this->assertNull(yield $handle->close());
    }

    public function testWriteAfterEnd(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "c+");
        $this->assertSame(0, $handle->tell());
        yield $handle->end("foo");

        $this->expectException(ClosedException::class);
        yield $handle->write("bar");
    }

    public function testWriteInAppendMode(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "a+");
        $this->assertSame(0, $handle->tell());
        yield $handle->write("bar");
        yield $handle->write("foo");
        yield $handle->write("baz");
        $this->assertSame(9, $handle->tell());
        yield $handle->seek(0);
        $this->assertSame(0, $handle->tell());
        $this->assertSame("barfoobaz", yield $handle->read());
    }

    public function testReadingToEof(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        $contents = "";
        $position = 0;

        $stat = yield $this->driver->getStatus(__FILE__);
        $chunkSize = (int) \floor(($stat["size"] / 5));

        while (!$handle->eof()) {
            $chunk = yield $handle->read($chunkSize);
            $contents .= $chunk;
            $position += \strlen($chunk);
            $this->assertSame($position, $handle->tell());
        }

        $this->assertNull(yield $handle->read());
        $this->assertSame(yield $this->driver->read(__FILE__), $contents);

        yield $handle->close();
    }

    public function testSequentialReads(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");

        $contents = "";
        $contents .= yield $handle->read(10);
        $contents .= yield $handle->read(10);

        $expected = \substr(yield $this->driver->read(__FILE__), 0, 20);
        $this->assertSame($expected, $contents);

        yield $handle->close();
    }

    public function testReadingFromOffset(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        yield $handle->seek(10);
        $this->assertSame(10, $handle->tell());
        $chunk = yield $handle->read(90);
        $this->assertSame(100, $handle->tell());
        $expected = \substr(yield $this->driver->read(__FILE__), 10, 90);
        $this->assertSame($expected, $chunk);

        yield $handle->close();
    }

    public function testSeekThrowsOnInvalidWhence(): \Generator
    {
        $this->expectException(\Error::class);

        try {
            /** @var File\File $handle */
            $handle = yield $this->driver->openFile(__FILE__, "r");
            yield $handle->seek(0, 99999);
        } finally {
            yield $handle->close();
        }
    }

    public function testSeekSetCur(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        yield $handle->seek(10);
        $this->assertSame(10, $handle->tell());
        yield $handle->seek(-10, \SEEK_CUR);
        $this->assertSame(0, $handle->tell());
        yield $handle->close();
    }

    public function testSeekSetEnd(): \Generator
    {
        $size = \filesize(__FILE__);
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        $this->assertSame(0, $handle->tell());
        yield $handle->seek(-10, \SEEK_END);
        $this->assertSame($size - 10, $handle->tell());
        yield $handle->close();
    }

    public function testPath(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        $this->assertSame(__FILE__, $handle->getPath());
        yield $handle->close();
    }

    public function testMode(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        $this->assertSame("r", $handle->getMode());
        yield $handle->close();
    }

    public function testClose(): \Generator
    {
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile(__FILE__, "r");
        yield $handle->close();

        $this->expectException(ClosedException::class);
        yield $handle->read();
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToSmallerSize(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "c+");

        $handle->write("foo");
        yield $handle->write("bar");
        yield $handle->truncate(4);
        yield $handle->seek(0);
        $contents = yield $handle->read();
        $this->assertTrue($handle->eof());
        $this->assertSame("foob", $contents);

        yield $handle->write("bar");
        $this->assertSame(7, $handle->tell());
        yield $handle->seek(0);
        $contents = yield $handle->read();
        $this->assertSame("foobbar", $contents);

        yield $handle->close();
    }

    /**
     * @depends testWrite
     */
    public function testTruncateToLargerSize(): \Generator
    {
        $path = Fixture::path() . "/write";
        /** @var File\File $handle */
        $handle = yield $this->driver->openFile($path, "c+");

        yield $handle->write("foo");
        yield $handle->truncate(6);
        $this->assertSame(3, $handle->tell());
        yield $handle->seek(0);
        $contents = yield $handle->read();
        $this->assertTrue($handle->eof());
        $this->assertSame("foo\0\0\0", $contents);

        yield $handle->write("bar");
        $this->assertSame(9, $handle->tell());
        yield $handle->seek(0);
        $contents = yield $handle->read();
        $this->assertSame("foo\0\0\0bar", $contents);

        yield $handle->close();
    }

    abstract protected function createDriver(): File\Driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->createDriver();
    }
}
