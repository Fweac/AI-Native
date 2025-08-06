<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI-Native Default Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains default settings for the AI-Native
    | Laravel package. These settings can be overridden in your schema files.
    |
    */

    'defaults' => [
        'auth' => [
            'enabled' => true,
            'provider' => 'sanctum',
            'guards' => ['web', 'api'],
        ],

        'database' => [
            'connection' => 'mysql',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'cache' => [
            'enabled' => true,
            'default_ttl' => 3600,
            'tags' => ['ai-native'],
        ],

        'pagination' => [
            'per_page' => 15,
            'max_per_page' => 100,
        ],

        'validation' => [
            'stop_on_first_failure' => false,
        ],
    ],

    'generators' => [
        'models' => [
            'namespace' => 'App\\Models',
            'path' => 'app/Models',
        ],

        'controllers' => [
            'namespace' => 'App\\Http\\Controllers',
            'path' => 'app/Http/Controllers',
        ],

        'migrations' => [
            'path' => 'database/migrations',
        ],

        'routes' => [
            'file' => 'routes/ai-native-api.php',
            'prefix' => 'api',
            'middleware' => ['api'],
        ],
    ],

    'storage' => [
        'default_disk' => 'public',
        'max_file_size' => '10MB',
        'allowed_extensions' => [
            'images' => ['jpg', 'jpeg', 'png', 'gif', 'svg'],
            'documents' => ['pdf', 'doc', 'docx', 'txt'],
            'archives' => ['zip', 'rar', '7z'],
        ],
    ],
];