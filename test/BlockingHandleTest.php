<?php

namespace Amp\File\Test;

use Amp\File;

class BlockingHandleTest extends HandleTest
{
    protected function setUp(): void
    {
        parent::setUp();
        File\filesystem(new File\BlockingDriver);
    }
}
