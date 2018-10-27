<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\Loop;
use function Amp\asyncCall;

class BlockingDriverTest extends DriverTest
{
    protected function execute(callable $cb)
    {
        Loop::run(function () use ($cb) {
            File\filesystem(new File\BlockingDriver);
            asyncCall($cb);
        });
    }
}
