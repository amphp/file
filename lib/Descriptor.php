<?php

namespace Amp\Fs;

use Amp\Promise;

interface Descriptor {
    /**
     * Read $len bytes from the open file handle starting at $offset
     *
     * @param int $offset
     * @param int $len
     * @return \Amp\Promise
     */
    public function read(int $offset, int $len): Promise;

    /**
     * Write $data to the open file handle starting at $offset
     *
     * @param int $offset
     * @param string $data
     * @return \Amp\Promise
     */
    public function write(int $offset, string $data): Promise;

    /**
     * Truncate the file to the specified $length
     *
     * Note: The descriptor must be opened for writing
     *
     * @param int $length
     * @return \Amp\Promise
     */
    public function truncate(int $length = 0): Promise;

    /**
     * Retrieve the filesystem stat array for the current descriptor
     *
     * @return \Amp\Promise
     */
    public function stat(): Promise;

    /**
     * Close the file handle
     *
     * @return \Amp\Promise
     */
    public function close(): Promise;
}
