<?php declare(strict_types=1);

namespace Amp\File;

/**
 * @psalm-suppress ClassMustBeFinal
 */
class FilesystemException extends \Exception
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
