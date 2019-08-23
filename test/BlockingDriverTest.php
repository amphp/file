<?php

namespace Amp\File\Test;

use Amp\File;

class BlockingDriverTest extends DriverTest
{
    protected function setUp(): void
    {
        parent::setUp();
        File\filesystem(new File\BlockingDriver);
    }
}
