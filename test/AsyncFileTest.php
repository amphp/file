<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\PendingOperationError;
use function Amp\coroutine;

abstract class AsyncFileTest extends FileTest
{
    public function testSimultaneousReads()
    {
        $this->expectException(PendingOperationError::class);

        $handle = $this->driver->openFile(__FILE__, "r");

        $promise1 = coroutine(fn() => $handle->read(20));
        $promise2 = coroutine(fn() => $handle->read(20));

        $expected = \substr(File\read(__FILE__), 0, 20);
        $this->assertSame($expected, $promise1->await());

        $promise2->await();
    }

    public function testSeekWhileReading()
    {
        $this->expectException(PendingOperationError::class);

        $handle = $this->driver->openFile(__FILE__, "r");

        $promise1 = coroutine(fn() => $handle->read(10));
        $promise2 = coroutine(fn() => $handle->read(0));

        $expected = \substr(File\read(__FILE__), 0, 10);
        $this->assertSame($expected, $promise1->await());

        $promise2->await();
    }

    public function testReadWhileWriting()
    {
        $this->expectException(PendingOperationError::class);

        $path = Fixture::path() . "/temp";

        $handle = $this->driver->openFile($path, "c+");

        $data = "test";

        $promise1 = $handle->write($data);
        $promise2 = coroutine(fn() => $handle->read(10));

        $this->assertNull($promise1->await());

        $promise2->await();
    }

    public function testWriteWhileReading()
    {
        $this->expectException(PendingOperationError::class);

        $path = Fixture::path() . "/temp";

        $handle = $this->driver->openFile($path, "c+");

        $promise1 = coroutine(fn() => $handle->read(10));
        $promise2 = $handle->write("test");

        $this->assertNull($promise1->await());

        $promise2->await();
    }
}
