<?php

namespace Amp\File;

final class PendingOperationError extends \Error
{
    public function __construct(
        string $message = "The previous file operation must complete before another can be started",
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $previous);
    }
}
