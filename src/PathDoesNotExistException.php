<?php

namespace Amp\File;

class PathDoesNotExistException extends FileSystemException
{
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        parent::__construct(sprintf('Specified path "%s" does not exist', $path));
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
