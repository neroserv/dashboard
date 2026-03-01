<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ensure tests never run against the real database. If you see the exception below,
     * the config cache was likely recreated (e.g. via config:cache). The test bootstrap
     * normally removes bootstrap/cache/config.php before each run so that phpunit.xml
     * env (DB_CONNECTION=sqlite, DB_DATABASE=:memory:) is used. Run: php artisan config:clear
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (app()->environment('testing')) {
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            $driver = config("database.connections.{$connection}.driver");
            if ($driver !== 'sqlite' || $database !== ':memory:') {
                throw new \RuntimeException(
                    'Tests must use SQLite in-memory database to avoid wiping your real data. '
                    ."Current: driver={$driver}, database={$database}. "
                    .'Run: php artisan config:clear and run the tests again.'
                );
            }
        }
    }
}
