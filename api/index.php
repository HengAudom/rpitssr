<?php

// Ensure /tmp folders exist for storage in serverless environments
$tmpStorage = '/tmp/storage';
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

// Copy persistent app json files if not present in /tmp
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
// Fix Vercel Serverless SCRIPT_NAME / baseUrl issue:
// Vercel serverless executes from /api/index.php.
// Without overriding SCRIPT_NAME to /index.php, Symfony Request detects '/api' as the application baseUrl,
// which strips '/api' from all incoming API routes (causing 405 Method Not Allowed) and prepends '/api' to asset URLs.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
unset($_SERVER['PATH_INFO']);
unset($_SERVER['ORIG_SCRIPT_NAME']);

require __DIR__ . '/../public/index.php';
