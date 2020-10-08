<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\File\PendingOperationError;
use function Amp\async;
use function Amp\await;

abstract class AsyncFileTest extends FileTest
{
    public function testSimultaneousReads()
    {
        $this->expectException(PendingOperationError::class);

        $handle = File\openFile(__FILE__, "r");

        $promise1 = async(fn() => $handle->read(20));
        $promise2 = async(fn() => $handle->read(20));

        $expected = \substr(File\read(__FILE__), 0, 20);
        $this->assertSame($expected, await($promise1));

        await($promise2);
    }

    public function testSeekWhileReading()
    {
        $this->expectException(PendingOperationError::class);

        $handle = File\openFile(__FILE__, "r");

        $promise1 = async(fn() => $handle->read(10));
        $promise2 = async(fn() => $handle->read(0));

        $expected = \substr(File\read(__FILE__), 0, 10);
        $this->assertSame($expected, await($promise1));

        await($promise2);
    }

    public function testReadWhileWriting()
    {
        $this->expectException(PendingOperationError::class);

        $path = Fixture::path() . "/temp";

        $handle = File\openFile($path, "c+");

        $data = "test";

        $promise1 = async(fn() => $handle->write($data));
        $promise2 = async(fn() => $handle->read(10));

        $this->assertNull(await($promise1));

        await($promise2);
    }

    public function testWriteWhileReading()
    {
        $this->expectException(PendingOperationError::class);

        $path = Fixture::path() . "/temp";

        $handle = File\openFile($path, "c+");

        $promise1 = async(fn() => $handle->read(10));
        $promise2 = async(fn() => $handle->write("test"));

        $this->assertNull(await($promise1));

        await($promise2);
    }
}
