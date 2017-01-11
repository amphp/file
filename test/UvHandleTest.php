<?php

namespace Amp\File\Test;

use Amp as amp;
use Amp\File as file;

class UvHandleTest extends HandleTest {
    protected function lRun(callable $cb) {
        if (\extension_loaded("uv")) {
            $loop = new \Amp\Loop\UvLoop;
            \AsyncInterop\Loop::execute(function() use ($cb, $loop) {
                \Amp\File\filesystem(new \Amp\File\UvDriver($loop));
                \Amp\rethrow(new \Amp\Coroutine($cb()));
            }, $loop);
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }

    public function testQueuedWritesOverrideEachOtherIfNotWaitedUpon() {
        $this->lRun(function () {
            $path = Fixture::path() . "/write";
            $handle = (yield file\open($path, "c+"));
            $this->assertSame(0, $handle->tell());

            $write1 = $handle->write("foo");
            $write2 = $handle->write("bar");

            yield amp\all([$write1, $write2]);

            $handle->seek(0);
            $contents = (yield $handle->read(8192));
            $this->assertSame(3, $handle->tell());
            $this->assertTrue($handle->eof());
            $this->assertSame("bar", $contents);

            yield $handle->close();
            yield file\unlink($path);
        });
    }
}
