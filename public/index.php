<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Suppress PHP 8.5 deprecation notices from vendor files (PDO::MYSQL_ATTR_SSL_CA)
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// NOTE: No REQUEST_URI stripping here. The sundal/index.php entry point
// keeps REQUEST_URI as /sundal/... so Symfony auto-detects /sundal as base URL.

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Load extra package autoloader (packages in vendor/ but not in git-committed autoload maps)
require __DIR__.'/../bootstrap/autoload-packages.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
