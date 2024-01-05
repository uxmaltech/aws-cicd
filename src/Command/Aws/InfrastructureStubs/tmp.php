<?php

return [
    'app' => [
        'name' => '@app.name@',
        'prefix' => '@app.prefix@',
        'key' => '@app.key@',
        'domain' => '@app.domain@',
        'subdomain' => '@app.subdomain@',
        'service_ports' => [
            'external' => [
                'http' => 80,
                'https' => 443,
            ],
            'internal' => [
                'php-fpm' => 9000,
                'nginx' => 80,
            ],
        ],
        'vpc_network' => '@app.vpc_network@',
        'vpc_netmask' => '@app.vpc_netmask@',
        'vpc_subnet_netmask' => '@app.vpc_subnet_netmask@',

    ],
    'vpc' => [
        'name' => '@app.prefix@-vpc',
        'id' => null,
        'cidr' => '@app.vpc_network@/@app.vpc_netmask@',
        'internet_gateway' => [
            'name' => '@app.prefix@-igw',
            'id' => null,
        ],
        'nat_gateway' => [
            'name' => '@app.prefix@-natgw',
            'elastic_ip' => null,
            'id' => null,
        ],
        'subnets' => [
            '@app.prefix@-subnet-private-awz-az1' => [
                'availability_zone' => 'az1',
                'access_type' => 'private',
                'cidr' => '@app.next_vpc_subnet_network_1@',
                'id' => null,
            ],
            '@app.prefix@-subnet-private-awz-az2' => [
                'availability_zone' => 'az2',
                'access_type' => 'private',
                'cidr' => '@app.next_vpc_subnet_network_2@',
                'id' => null,
            ],
            '@app.prefix@-subnet-public-aws-az1' => [
                'availability_zone' => 'az1',
                'access_type' => 'public-igw',
                'cidr' => '@app.next_vpc_subnet_network_3@',
                'id' => null,
            ],
            '@app.prefix@-subnet-public-aws-az2' => [
                'availability_zone' => 'az2',
                'access_type' => 'public-igw',
                'cidr' => '@app.next_vpc_subnet_network_4@',
                'id' => null,
            ],
            '@app.prefix@-subnet-ecs-@app.name@-php-fpm-public-nat' => [
                'availability_zone' => 'az1',
                'access_type' => 'public-nat',
                'cidr' => '@app.next_vpc_subnet_network_5@',
                'id' => null,
            ],
        ],
    ],
    'route53' => [
        'external_hosted_zone' => [
            'id' => null,
            'name' => '@app.domain@',
        ],
        'internal_hosted_zone' => [
            'id' => null,
            'name' => '@app.internal-domain@.intranet',
        ],
    ],
    'ecs' => [
        'cluster' => [
            'name' => '@app.prefix@-@app.name@-ecs',
            'arn' => null,
        ],
        'services' => [
            'php-fpm' => [
                'name' => '@app.prefix@-@app.name@-php-fpm-service',
                'arn' => null,
            ],
            'nginx' => [
                'name' => '@app.prefix@-@app.name@-nginx-service',
                'arn' => null,
            ],
        ],
    ],
    'task_definitions' => [
        'php-fpm' => [
            'name' => '@app.prefix@-@app.name@-php-fpm-task',
            'arn' => null,
            'jsonFile' => '@ecs.task_definitions.php-fpm.json@',
        ],
        'nginx' => [
            'name' => '@app.prefix@-@app.name@-nginx-task',
            'arn' => null,
            'jsonFile' => '@ecs.task_definitions.nginx.json@',
        ],
    ],
];
