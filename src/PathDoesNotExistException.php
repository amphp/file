<?php

namespace Amp\File;

class PathDoesNotExistException extends FileSystemException
{
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Specified path "%s" does not exist', $path));
    }
}
