<?php
return [
    'proxy' => '@ecr.proxy@',
    'timezone' => 'America/Mexico_City',
    'apache-php' => [
        'push-base-image' => @ecr.apache-php.base-push@,
        'base-image' => '@ecr.apache-php.base-image@',
        'app-image' => '@ecr.apache-php.image@',
        'exposed-port' => '80/tcp',
        'latest-base-image' => null,
        'latest-app-image' => null,
    ],
];