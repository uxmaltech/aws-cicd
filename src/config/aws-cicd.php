<?php

return [
    'cluster' => [
        'name' => env('APP_NAME', 'Laravel'),
        'port' => 443,
        'subdomain' => 'workshop',
        'domain' => 'personalizalo.mx',
        'app-nginx-service' => 'nginx-service',
        'app-php-fpm-service' => 'php-fpm-service',
        'aws' => [
            'vpc_id' => 'vpc-0ab7c3570e400bcad',
        ],
    ],
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
    ],
    'dockerized' => [
        'containers' => [
            'php-fpm' => [
                'tag' => 'latest',
                'username' => 'personalizalo',
                'alpineVersion' => '3.19',
                'phpVersion' => '8.2',
            ],
            'nginx' => [
                'tag' => 'latest',
                'username' => 'personalizalo',
                'alpineVersion' => '3.19',
                'nginxVersion' => '1.21',
            ],
        ],
    ],
    'images' => [
        'version' => 'latest',
        'username' => 'uxmaltech',
        'alpineVersion' => '3.19',
        'phpVersion' => '8.2',
    ],
    'uxmaltech' => [
        'devMode' => env('UXMALTECH_DEVMODE', false),
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
            /**
             * repositories
             * [
             * 'base-php-fpm' => 'dockerized.containers.php-fpm.username/php8.2-fpm-alpine3.19',
             * 'base-nginx' => 'dockerized.containers.nginx.username/nginx-alpine3.19',
             * 'app-php-fpm' => 'dockerized.containers.php-fpm.username/php-fpm-{app_name}',
             * 'app-nginx' => 'dockerized.containers.nginx.username/nginx-{app_name}',
             * ]
             */
            'repositories' => [],
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
            ],
        ],
    ],
];
