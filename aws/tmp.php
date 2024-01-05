<?php

return [
    'app' => [
        'name' => 'backoffice-ui',
        'env' => 'production',
        'key' => 'APP_KEY',
        'domain' => 'uxmal.net',
        'subdomain' => 'devtools',
    ],
    'vpc' => [
        'name' => 'uxdt-vpc',
        'id' => 'vpc-0ab7c3570e400bcad',
        'cidr' => '192.168.0.0/16',
        'subnets' => [
            'uxdt-subnet-private-awz-az1' => [
                'access_type' => 'private', // private, public-igw, public-nat
                'cidr' => '192.168.0.0/24',
            ],
            'uxdt-subnet-private-awz-az2' => [
                'access_type' => 'private', // private, public-igw, public-nat
                'cidr' => '192.168.1.0/24',
            ],
            'uxdt-subnet-public-aws-az1' => [
                'access_type' => 'public-igw', // private, public-igw, public-nat
                'cidr' => '192.168.2.0/24',
            ],
            'uxdt-subnet-public-aws-az2' => [
                'access_type' => 'public-igw', // private, public-igw, public-nat
                'cidr' => '192.168.3.0/24',
            ],
            'uxdt-subnet-ecs-{uxdt:app.name}-private' => [
                'access_type' => 'public-nat', // private, public-igw, public-nat
                'cidr' => '192.168.5.0/25',
            ],
        ],
        'security-groups' => [
            'uxdt-sg-ecs-private' => [
                'description' => 'Uxmal Devtools ECS private Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
                ],
            ],
            'uxdt-sg-ecs-public' => [
                'description' => 'Uxmal Devtools ECS public Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
                ],
            ],
            'uxdt-sg-ecs-rds' => [
                'description' => 'Uxmal Devtools ECS RDS Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
                ],
            ],
            'uxdt-sg-ecs-elb-internal' => [
                'description' => 'Uxmal Devtools ECS ELB internal Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
                ],
            ],
            'uxdt-sg-ecs-elb-external' => [
                'description' => 'Uxmal Devtools ECS ELB external Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
                    [
                        'protocol' => 'tcp',
                        'port' => 'https',
                        'cidr' => '0.0.0.0/0',
                    ],
                ],
            ],
            'uxdt-sg-ecs-{uxdt:app.name}-nginx' => [
                'description' => 'Uxmal Devtools ECS nginx-svc Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
                ],
            ],
            'uxdt-sg-ecs-{uxdt:app.name}-php-fpm' => [
                'description' => 'Uxmal Devtools ECS php-fpm-svc Security Group',
                'ingress' => [
                    [
                        'protocol' => 'all',
                        'port' => 'all',
                        'cidr' => 'uxdt:vpc.cidr',
                    ],
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
                    'uxdt-subnet-private',
                    'uxdt-subnet-ecs-{uxdt:app.name}-private',
                    'uxdt-subnet-ecs-{app1}-private',
                ],
                'security_groups' => [
                    'uxdt-sg-ecs-elb-internal',
                ],
            ],
            'external' => [
                'name' => 'uxdt-ecs-elb-external',
                'scheme' => 'internet-facing',
                'subnets' => [
                    'uxdt-subnet-public',
                ],
                'security_groups' => [
                    'uxdt-sg-ecs-elb-external',
                    'uxdt-subnet-ecs-{uxdt:app.name}-private',
                ],
            ],
        ],
    ],
    'route53' => [
        'uxdt-internal-dns' => [
            'domain' => 'uxdt-internal-dns.aws',
            'records' => [
                '{uxdt:app.name}-nginx.uxdt-internal-dns.aws' => [
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
                '{uxdt:app.name}' => [
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
                'uxdt-ecs-{uxdt:app.name}-nginx-svc' => [
                    'task' => [
                        'uxdt-ecs-{uxdt:app.name}-nginx-task' => [
                            'uxdt-ecs-{uxdt:app.name}-nginx-container',
                        ],
                    ],
                ],
            ],
            'uxdt-ecs-{uxdt:app.name}-php-fpm-svc' => [
                'task' => [
                    'uxdt-ecs-{uxdt:app.name}-php-fpm-task' => [
                        'uxdt-ecs-{uxdt:app.name}-php-fpm-container',
                    ],
                ],
            ],
        ],
    ],
];
