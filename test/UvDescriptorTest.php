<?php

namespace Amp\Fs\Test;

use Amp\{ Reactor, UvReactor };
use Amp\Fs\{ Filesystem, UvFilesystem };

class UvDescriptorTest extends DescriptorTest {
    protected function getReactor(): Reactor {
        return new UvReactor;
    }
    protected function getFilesystem(Reactor $reactor): Filesystem {
        return new UvFilesystem($reactor);
    }
}
