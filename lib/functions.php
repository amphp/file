<?php

namespace Amp\Fs;

/**
 * Get the global default filesystem instance
 *
 * @param \Amp\Fs\Filesystem $assign Optionally specify a new default filesystem instance
 * @return \Amp\Fs\Filesystem Returns the default filesystem instance
 */
function fs(Filesystem $assign = null) {
    static $filesystem;
    if ($assign) {
        return ($filesystem = $assign);
    } elseif ($filesystem) {
        return $filesystem;
    } elseif (\extension_loaded("uv")) {
        return ($filesystem = new UvFilesystem(\Amp\reactor()));
    } elseif (\extension_loaded("eio")) {
        return ($filesystem = new EioFilesystem(\Amp\reactor()));
    } else {
        return ($filesystem = new BlockingFilesystem(\Amp\reactor()));
    }
}
