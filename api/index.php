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
}

require __DIR__ . '/../public/index.php';
