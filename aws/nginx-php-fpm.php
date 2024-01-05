<?php

return [
    'app' => [
        'name' => 'backoffice-ui',
        'prefix' => 'uxdt',
        'env' => 'production',
        'key' => 'APP_KEY',
        'domain' => 'uxmal.net',
        'subdomain' => 'devtools',
    ],
    'vpc' => [
        'name' => 'uxdt-vpc',
        'id' => 'vpc-0e7ddc80f993b1d7e',
        'cidr' => '192.168.0.0/16',
        'internet_gateway' => [
            'name' => 'uxdt-backoffice-ui-igw',
            'id' => 'igw-07ef917754e79f4eb',
        ],
        'nat_gateway' => [
            'name' => 'uxdt-backoffice-ui-natgw',
            'elastic_ip' => 'eipalloc-09a39bd0d73e84c49',
            'id' => 'nat-0d63a8aa6365d2601',
        ],
        'subnets' => [
            'uxdt-subnet-private-awz-az1' => [
                'access_type' => 'private',
                'cidr' => '192.168.0.0/24',
                'id' => 'subnet-020821e5834c24e88',
            ],
            'uxdt-subnet-private-awz-az2' => [
                'access_type' => 'private',
                'cidr' => '192.168.1.0/24',
                'id' => 'subnet-0521a9ccc608f0658',
            ],
            'uxdt-subnet-public-aws-az1' => [
                'access_type' => 'public-igw',
                'cidr' => '192.168.2.0/24',
                'id' => 'subnet-0930aeb3a0a6dd3cc',
            ],
            'uxdt-subnet-public-aws-az2' => [
                'access_type' => 'public-igw',
                'cidr' => '192.168.3.0/24',
                'id' => 'subnet-02a96f91bd93213bd',
            ],
            'uxdt-subnet-ecs-backoffice-ui-php-fpm-private' => [
                'access_type' => 'public-nat',
                'cidr' => '192.168.4.0/24',
                'id' => 'subnet-00d7a09481c3a785c',
            ],
        ],
    ],
    'security-groups' => [
        'uxdt-sg-ecs-private' => [
            'description' => 'Uxmal Devtools ECS private Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
            ],
        ],
        'uxdt-sg-ecs-public' => [
            'description' => 'Uxmal Devtools ECS public Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
            ],
        ],
        'uxdt-sg-ecs-rds' => [
            'description' => 'Uxmal Devtools ECS RDS Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
            ],
        ],
        'uxdt-sg-ecs-elb-internal' => [
            'description' => 'Uxmal Devtools ECS ELB internal Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
            ],
        ],
        'uxdt-sg-ecs-elb-external' => [
            'description' => 'Uxmal Devtools ECS ELB external Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
                1 => [
                    'protocol' => 'tcp',
                    'port' => 'https',
                    'cidr' => '0.0.0.0/0',
                ],
            ],
        ],
        'uxdt-sg-ecs-backoffice-ui-nginx' => [
            'description' => 'Uxmal Devtools ECS nginx-svc Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
            ],
        ],
        'uxdt-sg-ecs-backoffice-ui-php-fpm' => [
            'description' => 'Uxmal Devtools ECS php-fpm-svc Security Group',
            'ingress' => [
                0 => [
                    'protocol' => 'all',
                    'port' => 'all',
                    'cidr' => 'uxdt:vpc.cidr',
                ],
            ],
        ],
    ],
    'ec2' => [
        'elb' => [
            'internal' => [
                'name' => 'uxdt-ecs-elb-internal',
                'scheme' => 'internal',
                'subnets' => [
                    0 => 'uxdt-subnet-private',
                    1 => 'uxdt-subnet-ecs-backoffice-ui-private',
                    2 => 'uxdt-subnet-ecs-{app1}-private',
                ],
                'security_groups' => [
                    0 => 'uxdt-sg-ecs-elb-internal',
                ],
            ],
            'external' => [
                'name' => 'uxdt-ecs-elb-external',
                'scheme' => 'internet-facing',
                'subnets' => [
                    0 => 'uxdt-subnet-public',
                ],
                'security_groups' => [
                    0 => 'uxdt-sg-ecs-elb-external',
                    1 => 'uxdt-subnet-ecs-backoffice-ui-private',
                ],
            ],
        ],
    ],
    'route53' => [
        'uxdt-internal-dns' => [
            'domain' => 'uxdt-internal-dns.aws',
            'records' => [
                'backoffice-ui-nginx.uxdt-internal-dns.aws' => [
                    'name' => 'uxdt-internal-dns-record',
                    'type' => 'A',
                    'alias' => true,
                    'ttl' => 300,
                    'value' => '{uxdt:ec2.elb.internal}',
                ],
            ],
        ],
        'uxdt-external-dns' => [
            'domain' => '{uxdt:app.subdomain}.{uxdt:app.domain}',
            'records' => [
                'backoffice-ui' => [
                    'type' => 'A',
                    'alias' => true,
                    'ttl' => 300,
                    'value' => '{uxdt:ec2.lb.external}',
                ],
            ],
        ],
    ],
    'ecs' => [
        'cluster' => [
            'name' => 'uxdt-ecs-cluster',
            'services' => [
                'uxdt-ecs-backoffice-ui-nginx-svc' => [
                    'task' => [
                        'uxdt-ecs-backoffice-ui-nginx-task' => [
                            0 => 'uxdt-ecs-backoffice-ui-nginx-container',
                        ],
                    ],
                ],
            ],
            'uxdt-ecs-backoffice-ui-php-fpm-svc' => [
                'task' => [
                    'uxdt-ecs-backoffice-ui-php-fpm-task' => [
                        0 => 'uxdt-ecs-backoffice-ui-php-fpm-container',
                    ],
                ],
            ],
        ],
    ],
];
