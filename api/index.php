<?php

// Ensure /tmp folders exist for storage in serverless environments (cold-start only)
$tmpStorage = '/tmp/storage';
if (!is_dir($tmpStorage . '/framework/views')) {
    $dirs = [
        $tmpStorage . '/framework/views',
        $tmpStorage . '/framework/cache',
        $tmpStorage . '/framework/sessions',
        $tmpStorage . '/logs',
        $tmpStorage . '/app',
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    // Copy persistent app json files if present
    $sourceStorageApp = __DIR__ . '/../storage/app';
    if (is_dir($sourceStorageApp)) {
        $files = ['settings.json', 'permissions.json', 'schedule_days_years.json', 'skills_groups_durations.json'];
        foreach ($files as $file) {
            $src = $sourceStorageApp . '/' . $file;
            $dst = $tmpStorage . '/app/' . $file;
            if (file_exists($src) && !file_exists($dst)) {
                @copy($src, $dst);
            }
        }
    }
}

// Fix Vercel Serverless SCRIPT_NAME / baseUrl issue:
// Vercel serverless executes from /api/index.php.
// Without overriding SCRIPT_NAME to /index.php, Symfony Request detects '/api' as the application baseUrl,
// which strips '/api' from all incoming API routes (causing 405 Method Not Allowed) and prepends '/api' to asset URLs.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = realpath(__DIR__ . '/../public/index.php') ?: (__DIR__ . '/../public/index.php');
$_SERVER['PHP_SELF'] = '/index.php';
unset($_SERVER['PATH_INFO']);
unset($_SERVER['ORIG_SCRIPT_NAME']);

// Ensure REMOTE_ADDR is always a valid non-empty string to prevent Symfony IpUtils TypeError on Vercel
if (empty($_SERVER['REMOTE_ADDR']) || !is_string($_SERVER['REMOTE_ADDR'])) {
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? '127.0.0.1';
    if (is_string($forwarded) && str_contains($forwarded, ',')) {
        $forwarded = trim(explode(',', $forwarded)[0]);
    }
    $_SERVER['REMOTE_ADDR'] = (!empty($forwarded) && is_string($forwarded)) ? $forwarded : '127.0.0.1';
}

require __DIR__ . '/../public/index.php';