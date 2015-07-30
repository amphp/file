<?php

namespace Amp\Filesystem\Test;

class UvDriverTest extends DriverTest {
    protected function setUp() {
        if (\extension_loaded("uv")) {
            $reactor = new \Amp\UvReactor;
            \Amp\reactor($reactor);
            $driver = new \Amp\Filesystem\UvDriver($reactor);
            \Amp\Filesystem\filesystem($driver);
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
