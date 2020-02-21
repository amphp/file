<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\PendingOperationError;

abstract class AsyncFileTest extends FileTest
{
    public function testSimultaneousReads(): \Generator
    {
        $this->expectException(PendingOperationError::class);

        /** @var \Amp\File\File $handle */
        $handle = yield File\open(__FILE__, "r");

        $promise1 = $handle->read();
        $promise2 = $handle->read();

        $expected = \substr(yield File\get(__FILE__), 0, 20);
        $this->assertSame($expected, yield $promise1);

        yield $promise2;
    }

    public function testSeekWhileReading(): \Generator
    {
        $this->expectException(PendingOperationError::class);

        /** @var \Amp\File\File $handle */
        $handle = yield File\open(__FILE__, "r");

        $promise1 = $handle->read(10);
        $promise2 = $handle->seek(0);

        $expected = \substr(yield File\get(__FILE__), 0, 10);
        $this->assertSame($expected, yield $promise1);

        yield $promise2;
    }

    public function testReadWhileWriting(): \Generator
    {
        $this->expectException(PendingOperationError::class);

        $path = Fixture::path() . "/temp";

        /** @var \Amp\File\File $handle */
        $handle = yield File\open($path, "c+");

        $data = "test";

        $promise1 = $handle->write($data);
        $promise2 = $handle->read(10);

        $this->assertSame(\strlen($data), yield $promise1);

        yield $promise2; // Should throw.
    }

    public function testWriteWhileReading(): \Generator
    {
        $this->expectException(PendingOperationError::class);

        $path = Fixture::path() . "/temp";

        /** @var \Amp\File\File $handle */
        $handle = yield File\open($path, "c+");

        $promise1 = $handle->read(10);
        $promise2 = $handle->write("test");

        $expected = \substr(yield File\get(__FILE__), 0, 10);
        $this->assertSame($expected, yield $promise1);

        yield $promise2; // Should throw.
    }
}
