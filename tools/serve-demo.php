<?php

/**
 * Menjalankan aplikasi dengan database demo terpisah (port 3399), bukan database asli.
 *
 * Nyalakan dulu MariaDB demo:
 *   C:\xampp\mysql\bin\mysqld.exe --no-defaults --datadir=C:\xampp\demo_mdb --port=3399 --bind-address=127.0.0.1
 *
 * Lalu:  php tools/serve-demo.php   (buka http://localhost:8800)
 */

$env = [
    'DB_CONNECTION'    => 'mysql',
    'DB_HOST'          => '127.0.0.1',
    'DB_PORT'          => '3399',
    'DB_DATABASE'      => 'fnb_demo',
    'DB_USERNAME'      => 'root',
    'DB_PASSWORD'      => '',
    'MAIL_MAILER'      => 'log',
    'SESSION_DRIVER'   => 'file',
    'CACHE_STORE'      => 'file',
    'QUEUE_CONNECTION' => 'sync',
    'APP_URL'          => 'http://localhost:8800',
];

foreach ($env as $key => $value) {
    putenv("$key=$value");
}

chdir(dirname(__DIR__));
passthru('"' . PHP_BINARY . '" artisan serve --port=8800');
