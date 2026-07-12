<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $path = storage_path('framework/testing/content');
        File::ensureDirectoryExists($path);
        config(['content.path' => $path]);
    }
}
