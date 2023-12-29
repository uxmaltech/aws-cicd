<?php

return [
    'laravel' => [
        'app' => [
            'name' => env('APP_NAME', 'Laravel'),
            'env' => env('APP_ENV', 'production'),
            'key' => env('APP_KEY'),
            'debug' => env('APP_DEBUG', false),
            'url' => env('APP_URL', 'http://localhost'),
            'author' => 'Enrique Martinez',
            'email' => 'enmaca@hotmail.com',
            'timezone' => 'America/Monterrey',
        ],
        'dockerized' => [
            'containers' => [
                'php-fpm' => [
                    'tag' => 'latest',
                    'username' => 'uxmaltech',
                    'alpineVersion' => '3.19',
                    'phpVersion' => '8.2'
                ],
                'nginx' => [
                    'tag' => 'latest',
                    'username' => 'uxmaltech',
                    'alpineVersion' => '3.19',
                    'nginxVersion' => '1.21'
                ]
            ]
        ]
    ],
    'images' => [
        'version' => 'latest',
        'username' => 'uxmaltech',
        'alpineVersion' => '3.19',
        'phpVersion' => '8.2',
    ],
    'uxmaltech' => [
        'personalAccessToken' => env('UXMALTECH_PERSONAL_ACCESS_TOKEN'),
    ],
    'aws' => [
        'credentials' => [
            'access_key' => env('AWS_ACCESS_KEY_ID'),
            'secret_key' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
        ],
        'ecr' => [
            'region' => 'us-east-1',
            'repository' => [
                'uri' => 'uxmaltech',
                'username' => 'uxmaltech'
            ],
            'profile' => 'default',
        ],
        'ecs' => [
            'service' => [
                'php-fpm' => [
                    'cluster_name' => 'php-fpm-cluster-ecs',
                    'cluster_port' => 9000,
                ],
                'nginx' => [
                    'cluster_name' => 'nginx-cluster-ecs',
                    'cluster_port' => 8000,
                ],
                'port' => 8000,
            ]
        ],
    ]
];
