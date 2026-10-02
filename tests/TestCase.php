<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Load custom vendor package autoloader (DomPDF, Excel, Google Client, etc.)
        $autoload = dirname(__DIR__) . '/bootstrap/autoload-packages.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        parent::setUp();

        // Disable FK checks for MySQL test database
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        parent::tearDown();
    }
}
