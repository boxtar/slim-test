<?php

use DI\ContainerBuilder;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'production' => false,

            // Should be set to false in production
            'displayErrorDetails' => true,

            // Base url to app
            'app_url' => "https://slim4-codecourse.dev",

            // Base url to public storage (requires symlink similar to Laravel)
            'public_storage' => "https://slim4-codecourse.dev/storage/app/public",

            // OS path to actual public storage dir
            'uploads_dir' => __DIR__ . '/../storage/app/public',

            // Database config
            'db' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'database' => 'slim4_codecourse',
                'username' => 'root',
                'password' => '',
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ]
        ],
    ]);
};
