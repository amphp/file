<?php

namespace Amp\File;

use Interop\Async\Awaitable;

interface Handle {
    /**
     * Read $len bytes from the open file handle starting at $offset
     *
     * @param int $length
     * @return \Interop\Async\Awaitable<string>
     */
    public function read(int $length): Awaitable;

    /**
     * Write $data to the open file handle starting at $offset
     *
     * @param string $data
     * @return \Interop\Async\Awaitable<int>
     */
    public function write(string $data): Awaitable;

    /**
     * Close the file handle
     *
     * Applications are not required to manually close handles -- they will
     * be unloaded automatically when the object is garbage collected.
     *
     * @return \Interop\Async\Awaitable
     */
    public function close(): Awaitable;

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
    public function seek(int $position, int $whence = \SEEK_SET);

    /**
     * Return the current internal offset position of the file handle
     *
     * @return int
     */
    public function tell(): int;

    /**
     * Test for "end-of-file" on the file handle
     *
     * @return bool
     */
    public function eof(): bool;

    /**
     * Retrieve the path used when opening the file handle
     *
     * @return string
     */
    public function path(): string;

    /**
     * Retrieve the mode used when opening the file handle
     *
     * @return string
     */
    public function mode(): string;
}
