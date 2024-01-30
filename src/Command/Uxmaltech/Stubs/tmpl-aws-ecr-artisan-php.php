<?php
return [
    'proxy' => '@ecr.proxy@',
    'timezone' => 'America/Mexico_City',
    'artisan-php' => [
        'push-base-image' => @ecr.artisan-php.base-push@,
        'base-image' => '@ecr.artisan-php.base-image@',
        'app-image' => '@ecr.artisan-php.image@',
        'exposed-port' => '8000/tcp',
        'latest-base-image' => null,
        'latest-app-image' => null,
    ],
];