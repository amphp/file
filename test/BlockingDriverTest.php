<?php

namespace Amp\File\Test;

class BlockingDriverTest extends DriverTest {
    protected function setUp() {
        \Amp\reactor(\Amp\driver());
        \Amp\File\filesystem(new \Amp\File\BlockingDriver);
    }
}
