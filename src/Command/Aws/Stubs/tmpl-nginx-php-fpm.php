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
    'vpc' => [
        'name' => '@app.prefix@-vpc',
        'id' => null,
        'cidr' => '192.168.0.0/20',
        'internet_gateway' => [
            'name' => '@app.prefix@-igw',
            'id' => null,
        ],
        'vpc-endpoint-s3' => [
            'name' => '@app.prefix@-vpc-endpoint-s3',
            'id' => null,
        ],
        'nat_gateways' => [
            'natgw-az1' => [
                'subnet_key' => 'private-awz-az1',
                'elastic_ip' => null,
                'id' => null,
            ],
            'natgw-az2' => [
                'subnet_key' => 'private-awz-az2',
                'elastic_ip' => null,
                'id' => null,
            ],
        ],
        'subnets' => [
            'private-awz-az1' => [
                'name' => '@app.prefix@-subnet-private-az1',
                'availability_zone' => 'az1',
                'access_type' => 'main',
                'cidr' => '192.168.0.0/25',
                'id' => null,
            ],
            'private-awz-az2' => [
                'name' => '@app.prefix@-subnet-private-az2',
                'availability_zone' => 'az2',
                'access_type' => 'main',
                'cidr' => '192.168.0.128/25',
                'id' => null,
            ],
            'public-aws-az1' => [
                'name' => '@app.prefix@-subnet-public-az1',
                'availability_zone' => 'az1',
                'access_type' => 'public-igw',
                'cidr' => '192.168.1.0/25',
                'id' => null,
                'route-table-id' => null,
            ],
            'public-aws-az2' => [
                'name' => '@app.prefix@-subnet-public-az2',
                'availability_zone' => 'az2',
                'access_type' => 'public-igw',
                'cidr' => '192.168.1.128/25',
                'id' => null,
                'route-table-id' => null,
            ],
            'nat-aws-az1' => [
                'name' => '@app.prefix@-subnet-nat-az1',
                'availability_zone' => 'az1',
                'access_type' => 'public-nat',
                'cidr' => '192.168.2.0/25',
                'natgw_key' => 'natgw-az1',
                'id' => null,
                'route-table-id' => null,
            ],
            'nat-aws-az2' => [
                'name' => '@app.prefix@-subnet-nat-az2',
                'availability_zone' => 'az2',
                'access_type' => 'public-nat',
                'cidr' => '192.168.2.128/25',
                'natgw_key' => 'natgw-az2',
                'id' => null,
                'route-table-id' => null,
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
            'name' => '@app.internal_domain@',
        ],
    ],
    'ecs' => [
        'cluster' => [
            'name' => '@app.prefix@-@app.name@-ecs',
            'arn' => null,
        ],
    ],
];