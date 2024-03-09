<?php declare(strict_types=1);

namespace Amp\File\Test;

use Amp\File\KeyedFileMutex;
use Amp\Sync\AbstractKeyedMutexTest;
use Amp\Sync\KeyedMutex;

final class KeyedFileMutexTest extends AbstractKeyedMutexTest
{
    public function createMutex(): KeyedMutex
    {
        return new KeyedFileMutex(\sys_get_temp_dir() . '/testmutex-' . bin2hex(random_bytes(5)) . '-%s.lock');
    }
}
