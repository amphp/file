<?php

namespace Amp\File;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\Promise;

interface File extends InputStream, OutputStream
{
    public const DEFAULT_READ_LENGTH = 8192;

    public const SEEK_SET = \SEEK_SET;
    public const SEEK_CUR = \SEEK_CUR;
    public const SEEK_END = \SEEK_END;

    /**
     * Read $length bytes from the open file handle.
     *
     * @param int $length
     *
     * @return Promise<string|null>
     */
    public function read(int $length = self::DEFAULT_READ_LENGTH): Promise;

    /**
     * Write $data to the open file handle.
     *
     * @param string $data
     *
     * @return Promise<int>
     */
    public function write(string $data): Promise;

    /**
     * Write $data to the open file handle and close the handle once the write completes.
     *
     * @param string $data
     *
     * @return Promise<int>
     */
    public function end(string $data = ""): Promise;

    /**
     * Close the file handle.
     *
     * Applications are not required to manually close handles -- they will
     * be unloaded automatically when the object is garbage collected.
     *
     * @return Promise<void>
     */
    public function close(): Promise;

    /**
     * Set the handle's internal pointer position.
     *
     * $whence values:
     *
     * SEEK_SET - Set position equal to offset bytes.
     * SEEK_CUR - Set position to current location plus offset.
     * SEEK_END - Set position to end-of-file plus offset.
     *
     * @param int $position
     * @param int $whence
     *
     * @return Promise<int> New offset position.
     */
    public function seek(int $position, int $whence = self::SEEK_SET): Promise;

    /**
     * Return the current internal offset position of the file handle.
     *
     * @return int
     */
    public function tell(): int;

    /**
     * Test for "end-of-file" on the file handle.
     *
     * @return bool
     */
    public function eof(): bool;

    /**
     * Retrieve the path used when opening the file handle.
     *
     * @return string
     */
    public function getPath(): string;

    /**
     * Retrieve the mode used when opening the file handle.
     *
     * @return string
     */
    public function getMode(): string;

    /**
     * Truncates the file to the given length. If $size is larger than the current file size, the file is extended
     * with null bytes.
     *
     * @param int $size New file size.
     *
     * @return Promise<void>
     */
    public function truncate(int $size): Promise;
}
