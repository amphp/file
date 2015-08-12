<?php

namespace Amp\File\Test;

use Amp as amp;
use Amp\File as file;

class BlockingHandleTest extends HandleTest {
    protected function setUp() {
        $reactor = new amp\NativeReactor;
        amp\reactor($reactor);
        $driver = new file\BlockingDriver($reactor);
        file\filesystem($driver);
        parent::setUp();
    }
}
