<?php

namespace Amp\File;

use Amp\ByteStream\ClosableStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\OutputStream;
use Amp\ByteStream\SeekableStream;
use Amp\CancellationToken;

interface File extends InputStream, OutputStream, ClosableStream, SeekableStream
{
    public const DEFAULT_READ_LENGTH = 8192;

    /**
     * Read $length bytes from the open file handle.
     *
     * @param CancellationToken|null $token
     * @param int $length
     *
     * @return string|null
     */
    public function read(?CancellationToken $token = null, int $length = self::DEFAULT_READ_LENGTH): ?string;

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
     */
    public function truncate(int $size): void;
}
