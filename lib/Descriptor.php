<?php

namespace Amp\Fs;

interface Descriptor {
    /**
     * Read $len bytes from the open file handle starting at $offset
     *
     * @param int $offset
     * @param int $len
     * @return \Amp\Promise
     */
    public function read($offset, $len);

    /**
     * Write $data to the open file handle starting at $offset
     *
     * @param int $offset
     * @param string $data
     * @return \Amp\Promise
     */
    public function write($offset, $data);

    /**
     * Truncate the file to the specified $length
     *
     * Note: The descriptor must be opened for writing
     *
     * @param int $length
     * @return \Amp\Promise
     */
    public function truncate($length = 0);

    /**
     * Retrieve the filesystem stat array for the current descriptor
     *
     * @return \Amp\Promise
     */
    public function stat();

    /**
     * Close the file handle
     *
     * @return \Amp\Promise
     */
    public function close();
}
