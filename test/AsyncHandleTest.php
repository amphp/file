<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\PendingOperationError;

abstract class AsyncHandleTest extends HandleTest
{
    public function testSimultaneousReads()
    {
        $this->expectException(PendingOperationError::class);

        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->read();
            $promise2 = $handle->read();

            $expected = \substr(yield File\get(__FILE__), 0, 20);
            $this->assertSame($expected, yield $promise1);

            yield $promise2;
        });
    }

    public function testSeekWhileReading()
    {
        $this->expectException(PendingOperationError::class);

        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->read(10);
            $promise2 = $handle->seek(0);

            $expected = \substr(yield File\get(__FILE__), 0, 10);
            $this->assertSame($expected, yield $promise1);

            yield $promise2;
        });
    }

    public function testReadWhileWriting()
    {
        $this->expectException(PendingOperationError::class);

        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $data = "test";

            $promise1 = $handle->write($data);
            $promise2 = $handle->read(10);

            $this->assertSame(\strlen($data), yield $promise1);

            yield $promise2;
        });
    }

    public function testWriteWhileReading()
    {
        $this->expectException(PendingOperationError::class);

        $this->execute(function () {
            /** @var \Amp\File\Handle $handle */
            $handle = yield File\open(__FILE__, "r");

            $promise1 = $handle->read(10);
            $promise2 = $handle->write("test");

            $expected = \substr(yield File\get(__FILE__), 0, 10);
            $this->assertSame($expected, yield $promise1);

            yield $promise2;
        });
    }
}
