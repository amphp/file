<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\NativeReactor;
use Amp\Fs\BlockingFilesystem;

class BlockingFilesystemTest extends FilesystemTest {
    protected function getReactor() {
        return new NativeReactor;
    }
    protected function getFilesystem(Reactor $reactor) {
        return new BlockingFilesystem($reactor);
    }
}
