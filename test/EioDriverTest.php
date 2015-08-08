<?php

namespace Amp\File\Test;

class EioDriverTest extends DriverTest {
    protected function setUp() {
        if (extension_loaded("eio")) {
            \Amp\reactor(\Amp\driver());
            \Amp\File\filesystem(new \Amp\File\EioDriver);
        } else {
            $this->markTestSkipped(
                "eio extension not loaded"
            );
        }
    }
}
