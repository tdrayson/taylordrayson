<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Media Library writes to the `public` disk, and a test that forgets to fake
     * it stores into real project storage. That debris accumulates silently and
     * is indistinguishable from production media once it is there, so the disk
     * is faked for every test rather than per file.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }
}
