<?php

namespace Amp\File;

interface Handle {
    /**
     * Read $len bytes from the open file handle starting at $offset
     *
     * @param int $offset
     * @param int $len
     * @return \Amp\Promise
     */
    public function read($len);

    /**
     * Write $data to the open file handle starting at $offset
     *
     * @param int $offset
     * @param string $data
     * @return \Amp\Promise
     */
    public function write($data);

    /**
     * Close the file handle
     *
     * Applications are not required to manually close handles -- they will
     * be unloaded automatically when the object is garbage collected.
     *
     * @return \Amp\Promise
     */
    public function close();

    /**
     * Set the handle's internal pointer position
     *
     * $whence values:
     *
     * SEEK_SET - Set position equal to offset bytes.
     * SEEK_CUR - Set position to current location plus offset.
     * SEEK_END - Set position to end-of-file plus offset.
     *
     * @param int $position
     * @param int $whence
     * @return void
     */
    public function seek($position, $whence = \SEEK_SET);

    /**
     * Return the current internal offset position of the file handle
     *
     * @return int
     */
    public function tell();

    /**
     * Test for "end-of-file" on the file handle
     *
     * @return bool
     */
    public function eof();

    /**
     * Retrieve the path used when opening the file handle
     *
     * @return string
     */
    public function path();

    /**
     * Retrieve the mode used when opening the file handle
     *
     * @return string
     */
    public function mode();
}
