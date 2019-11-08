<?php

return array_merge(
    [
        'settings' => [
            'production' => false,

            // Should be set to false in production
            'displayErrorDetails' => false,

            // Base url to app
            'app_url' => "http://localhost",

            // Base url to public storage (requires symlink similar to Laravel)
            'public_storage' => "http://localhost/storage/app/public",

            // OS path to actual public storage dir
            'uploads_dir' => __DIR__ . '/../storage/app/public',

            // Database config
            'db' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'database' => 'slim4',
                'username' => 'root',
                'password' => '',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ]
        ],
    ],
    []
);
