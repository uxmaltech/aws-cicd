<?php

return [
    'laravel.app' => [
        'name' => env('APP_NAME', 'Laravel'),
        'env' => env('APP_ENV', 'production'),
        'key' => env('APP_KEY'),
        'debug' => env('APP_DEBUG', false),
        'url' => env('APP_URL', 'http://localhost'),
        'author' => 'Enrique Martinez',
        'email' => 'enmaca@hotmail.com'
    ],
    'images' => [
        'version' => 'latest',
        'username' => 'uxmaltech',
        'alpineVersion' => '3.19',
        'phpVersion' => '8.2',
    ],
    'uxmaltech' => [
        'personal_access_token' => env('UXMALTECH_PERSONAL_ACCESS_TOKEN'),
    ],
    'aws' => [
        'credentials' => [
            'access_key' => env('AWS_ACCESS_KEY_ID'),
            'secret_key' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
        ],
        'ecr' => [
            'region' => 'us-east-1',
            'repository_uri' => 'uxmaltech',
            'profile' => 'default',
        ],
    ]
];
