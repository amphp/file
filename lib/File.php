<?php

namespace Amp\File;

use Amp\Promise;

interface File extends Handle
{
    /**
     * Truncates the file to the given length. If $size is larger than the current file size, the file is extended
     * with null bytes.
     *
     * @param int $size New file size.
     *
     * @return \Amp\Promise
     */
    public function truncate(int $size): Promise;
}
