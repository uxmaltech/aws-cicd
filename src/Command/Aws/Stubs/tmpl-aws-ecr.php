<?php

return [
    'app' => [
        'name' => '@app.name@',
        'prefix' => '@app.prefix@',
        'key' => '@app.key@',
        'domain' => '@app.domain@',
        'subdomain' => '@app.subdomain@',
        'service_ports' => [
            'http' => 80,
            'https' => 443,
        ],
        'vpc_network' => '@app.vpc_network@',
        'vpc_netmask' => '@app.vpc_netmask@',
        'vpc_subnet_netmask' => '@app.vpc_subnet_netmask@',
        'php-fpm-cluster-port' => 9000,
        'nginx-cluster-port' => 80,
    ],
    'docker' => [
        'name' => '@app.prefix@-vpc',
    ],
];
