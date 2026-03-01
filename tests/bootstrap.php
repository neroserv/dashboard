<?php

/**
 * Run before Laravel boots. Force DB to SQLite :memory: so the real DB is never touched.
 *
 * Config and route caches are cleared during test bootstrap so that:
 * - phpunit.xml env vars (DB_CONNECTION=sqlite, DB_DATABASE=:memory:) are used instead of
 *   cached MySQL config (avoids RefreshDatabase wiping your development database).
 * - Routes are loaded from route files so newly added routes exist (avoids RouteNotFoundException).
 */
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

$cacheDir = __DIR__.'/../bootstrap/cache';
$configCache = $cacheDir.'/config.php';
$routesCache = $cacheDir.'/routes-v7.php';
if (file_exists($configCache)) {
    @unlink($configCache);
}
if (file_exists($routesCache)) {
    @unlink($routesCache);
}

require __DIR__.'/../vendor/autoload.php';
