<?php

namespace Amp\Filesystem\Test;

class EioDriverTest extends DriverTest {
    protected function setUp() {
        if (extension_loaded("eio")) {
            \Amp\reactor(\Amp\driver());
            \Amp\Filesystem\filesystem(new \Amp\Filesystem\EioDriver);
        } else {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }
    }
}
