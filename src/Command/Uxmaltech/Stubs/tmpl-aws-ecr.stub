<?php
return [
    'proxy' => '@ecr.proxy@',
    'timezone' => 'America/Mexico_City',
    'php-fpm' => [
        'push-base-image' => @ecr.php-fpm.base-push@,
        'base-image' => '@ecr.php-fpm.base-image@',
        'app-image' => '@ecr.php-fpm.image@',
        'exposed-port' => '9000/tcp',
        'latest-base-image' => null,
        'latest-app-image' => null,
    ],
    'nginx' => [
        'push-base-image' => @ecr.nginx.base-push@,
        'base-image' => '@ecr.nginx.base-image@',
        'app-image' => '@ecr.nginx.image@',
        'exposed-port' => '80/tcp',
        'latest-base-image' => null,
        'latest-app-image' => null,
    ],
];
