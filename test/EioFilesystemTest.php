<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\NativeReactor;
use Amp\Fs\EioFilesystem;

class EioFilesystemTest extends FilesystemTest {
    protected function getReactor() {
        if (extension_loaded("eio")) {
            return new NativeReactor;
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }

    protected function getFilesystem(Reactor $reactor) {
        return new EioFilesystem($reactor);
    }
}
