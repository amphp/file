<?php

namespace Amp\File;

use Amp\ByteStream\StreamException;

class FilesystemException extends StreamException {
    public function __construct(string $message, \Throwable $previous = null) {
        parent::__construct($message, 0, $previous);
    }
}
