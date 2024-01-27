<?php
return [
    'name' => '@app.name@',
    'prefix' => '@app.prefix@',
    'domain' => '@app.domain@',
    'subdomain' => '@app.subdomain@',
    'internal_domain' => '@app.internal_domain@',
    'mode' => '@app.mode@',
    'git-hub-personal-access-token' => env('GITHUB_PERSONAL_ACCESS_TOKEN'),
    'service_ports' => @app.service.ports@,
    'dev_mode' => true,
    'debug_mode' => true,
    'key' => env('APP_KEY'),
    'db' => [
        'engine' => env('DB_CONNECTION'),
        'host' => env('DB_HOST'),
        'port' => env('DB_PORT'),
        'database' => env('DB_DATABASE'),
        'username' => env('DB_USERNAME'),
        'password' => env('DB_PASSWORD'),
    ],
    'features' => [
        'app_dockerized' => @dockerized@,
        'aws_ecr_repository_managed' => @aws.ecr.repository.managed@,
        'aws_vpc_managed' => @aws.vpc.managed@,
        'aws_ecs_managed' => @aws.ecs.managed@,
    ],
    'author' => [
        'name' => 'UxmalTech',
        'email' => 'name@email.com',
    ],
];