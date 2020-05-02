<?php declare(strict_types = 1);

namespace Amp\File\Test\Sync;

use Amp\File\Sync\AsyncFileMutex;
use Amp\Sync\Mutex;
use Amp\Sync\Test\AbstractMutexTest;

final class AsyncFileMutexTest extends AbstractMutexTest
{
    public function createMutex(): Mutex
    {
        return new AsyncFileMutex(\tempnam(\sys_get_temp_dir(), 'mutex-') . '.lock');
    }
}
