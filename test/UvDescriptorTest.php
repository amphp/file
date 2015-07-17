<?php

namespace Amp\Fs\Test;

use Amp\Reactor;
use Amp\UvReactor;
use Amp\Fs\UvFilesystem;

class UvDescriptorTest extends DescriptorTest {
    protected function getReactor() {
        if (extension_loaded('uv')) {
            return new UvReactor;
        } else {
            $this->markTestSkipped(
                "php-uv extension not loaded"
            );
        }
    }
    protected function getFilesystem(Reactor $reactor) {
        return new UvFilesystem($reactor);
    }
}
