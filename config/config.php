<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Privatefy',
        'base_url' => '',
        'debug' => false,
        'timezone' => 'Europe/Berlin',
        'session_name' => 'PRIVATEFYSESSID',
        'session_lifetime_seconds' => 43200,
        'upload_max_bytes' => 104857600,
        'allowed_mime_types' => [
            'audio/mpeg',
            'audio/mp3',
            'audio/x-mpeg',
            'audio/x-mp3',
            'application/octet-stream'
        ],
        'storage_path' => dirname(__DIR__) . '/storage/music',
        'tmp_path' => dirname(__DIR__) . '/storage/tmp',
        'log_path' => dirname(__DIR__) . '/storage/logs/app.log',
        'cron_token' => 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET_64_CHARS_MINIMUM',
    ],
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'privatefy',
        'username' => 'privatefy_user',
        'password' => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],
];
