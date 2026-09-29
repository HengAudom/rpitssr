<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

@header_remove('X-Powered-By');
@ini_set('expose_php', 'off');

// Ensure REMOTE_ADDR is always a valid non-empty string to prevent Symfony IpUtils TypeError
if (empty($_SERVER['REMOTE_ADDR']) || !is_string($_SERVER['REMOTE_ADDR'])) {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? '127.0.0.1';
    if (is_string($forwarded) && str_contains($forwarded, ',')) {
        $forwarded = trim(explode(',', $forwarded)[0]);
    }
    $_SERVER['REMOTE_ADDR'] = (!empty($forwarded) && is_string($forwarded)) ? $forwarded : '127.0.0.1';
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
