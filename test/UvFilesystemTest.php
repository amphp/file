<?php

namespace Amp\Fs\Test;

class UvFilesystemTest extends FilesystemTest {
    protected function setUp() {
        if (\extension_loaded("uv")) {
            $reactor = new \Amp\UvReactor;
            \Amp\reactor($reactor);
            \Amp\Fs\filesystem(new \Amp\Fs\UvFilesystem($reactor));
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }

    public function testScandir() {
        $this->markTestSkipped("currently crashes php");
    }
}
