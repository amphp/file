<?php

namespace Amp\Fs;

use function Amp\reactor;

/**
 * Get the global default filesystem instance
 *
 * @param \Amp\Fs\Filesystem $assign Optionally specify a new default filesystem instance
 * @return \Amp\Fs\Filesystem Returns the default filesystem instance
 */
function fs(Filesystem $assign = null): Filesystem {
    static $filesystem;
    if ($assign) {
        return ($filesystem = $assign);
    } elseif ($filesystem) {
        return $filesystem;
    } elseif (\extension_loaded('uv')) {
        return ($filesystem = new UvFilesystem(reactor()));
    /*
    // @TODO
    } elseif (\extension_loaded("eio") {
        return ($filesystem = new EioFilesystem);
    }
    */
    } else {
        return ($filesystem = new BlockingFilesystem(reactor()));
    }
}
