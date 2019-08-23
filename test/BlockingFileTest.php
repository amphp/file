<?php

namespace Amp\File\Test;

use Amp\File;

class BlockingFileTest extends FileTest
{
    protected function setUp(): void
    {
        parent::setUp();
        File\filesystem(new File\BlockingDriver);
    }
}
