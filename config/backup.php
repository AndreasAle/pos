<?php

return [
    // Off by default: the schedule only runs when the server opts in.
    'enabled' => (bool) env('YOKA_BACKUP_ENABLED', false),
    // base64 of 32 random bytes: php -r "echo base64_encode(random_bytes(32));"
    'key' => env('YOKA_BACKUP_KEY'),
    'path' => env('YOKA_BACKUP_PATH', storage_path('app/backups')),
    'keep_days' => (int) env('YOKA_BACKUP_KEEP_DAYS', 14),
    'mysqldump' => env('YOKA_MYSQLDUMP_PATH', 'mysqldump'),
    'mysql' => env('YOKA_MYSQL_PATH', 'mysql'),
    // Failure alert goes here (and to the log).
    'alert_email' => env('YOKA_BACKUP_ALERT_EMAIL'),
];
