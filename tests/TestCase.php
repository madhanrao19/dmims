<?php

namespace Tests;

use App\Services\AccessControlService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The access-mode memo is process-static; clear it between tests so a
        // cached customer id from one test cannot leak into the next.
        AccessControlService::flushCache();
    }
}
