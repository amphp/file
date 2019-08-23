<?php

namespace Amp\File\Test;

use Amp\File;
use Amp\PHPUnit\AsyncTestCase;

abstract class FilesystemTest extends AsyncTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Fixture::init();
        File\StatCache::clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Fixture::clear();
    }
}
