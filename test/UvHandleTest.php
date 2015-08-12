<?php

namespace Amp\File\Test;

use Amp as amp;
use Amp\File as file;

class UvHandleTest extends HandleTest {
    protected function setUp() {
        if (\extension_loaded("uv")) {
            $reactor = new amp\UvReactor;
            amp\reactor($reactor);
            $driver = new file\UvDriver($reactor);
            file\filesystem($driver);
            parent::setUp();
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }

    public function testQueuedWritesOverrideEachOtherIfNotWaitedUpon() {
        amp\run(function () {
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
