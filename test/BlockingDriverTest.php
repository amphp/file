<?php

namespace Amp\Filesystem\Test;

class BlockingDriverTest extends DriverTest {
    protected function setUp() {
        \Amp\reactor(\Amp\init());
        \Amp\Filesystem\filesystem(new \Amp\Filesystem\BlockingDriver);
    }
}
