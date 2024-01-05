<?php

return [
    'app' => [
        'name' => 'workshop',
        'prefix' => 'personalizalo',
        'key' => 'base64:Wu2/d+LD1SHRY6onZ64a5di0q9J8syMSnbH/QENka+8=',
        'domain' => 'personalizalo.mx',
        'subdomain' => 'wmty01',
        'service_ports' => [
            'http' => 80,
            'https' => 443,
        ],
        'vpc_network' => '192.168.0.0',
        'vpc_netmask' => '20',
        'vpc_subnet_netmask' => '25',
        'php-fpm-cluster-port' => 9000,
        'nginx-cluster-port' => 80,
    ],
    'vpc' => [
        'name' => 'personalizalo-vpc',
        'id' => 'vpc-0b88754939642a334',
        'cidr' => '192.168.0.0/20',
        'internet_gateway' => [
            'name' => 'personalizalo-workshop-igw',
            'id' => 'igw-05098ef39bda0c6a1',
        ],
        'nat_gateway' => [
            'name' => 'personalizalo-workshop-natgw',
            'elastic_ip' => 'eipalloc-0397afcfea1e7a819',
            'id' => 'nat-0e4c9ab8b8a9c352f',
        ],
        'subnets' => [
            'personalizalo-subnet-private-awz-az1' => [
                'access_type' => 'private',
                'cidr' => '192.168.0.0/25',
                'id' => 'subnet-0e6fe47167afde45d',
            ],
            'personalizalo-subnet-private-awz-az2' => [
                'access_type' => 'private',
                'cidr' => '192.168.0.128/25',
                'id' => 'subnet-0b473a65bba50a860',
            ],
            'personalizalo-subnet-public-aws-az1' => [
                'access_type' => 'public-igw',
                'cidr' => '192.168.1.0/25',
                'id' => 'subnet-0a471443464a9fb49',
            ],
            'personalizalo-subnet-public-aws-az2' => [
                'access_type' => 'public-igw',
                'cidr' => '192.168.1.128/25',
                'id' => 'subnet-0072c100bf464e6aa',
            ],
            'personalizalo-subnet-ecs-workshop-php-fpm-public-nat' => [
                'access_type' => 'public-nat',
                'cidr' => '192.168.2.0/25',
                'id' => 'subnet-03c88b6413a80d75d',
            ],
        ],
    ],
    'route53' => [
        'external_hosted_zone' => [
            'id' => '/hostedzone/Z0315558G9YRS3UC2RZS',
            'name' => 'personalizalo.mx',
        ],
        'internal_hosted_zone' => [
            'id' => '/hostedzone/Z03291902MMDIUU2ID8H9',
            'name' => 'personalizalo-mx.intranet',
        ],
    ],
    'ecs' => [
        'cluster' => [
            'name' => 'personalizalo-workshop-ecs',
            'arn' => 'arn:aws:ecs:us-west-2:707660622854:cluster/personalizalo-workshop-ecs',
        ],
    ],
];
