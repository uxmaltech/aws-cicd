<?php

return
    [
        'name' => '@app.prefix@-vpc',
        'id' => null,
        'cidr' => '@vpc.network@/@vpc.netmask@',
        'internet_gateway' => [
            'name' => '@app.prefix@-igw',
            'id' => null,
        ],
        'vpc-endpoint-s3' => [
            'name' => '@app.prefix@-vpc-endpoint-s3',
            'id' => null,
        ],
        'nat_gateways' => [
            'natgw-aws-az1' => [
                'name' => '@app.prefix@-natgw-az1',
                'subnet_key' => 'public-awz-az1',
                'elastic_ip' => null,
                'id' => null,
            ],
            'natgw-aws-az2' => [
                'name' => '@app.prefix@-natgw-az2',
                'subnet_key' => 'public-awz-az2',
                'elastic_ip' => null,
                'id' => null,
            ],
        ],
        'subnets' => [
            'public-aws-az1' => [
                'name' => '@app.prefix@-subnet-public-az1',
                'availability_zone' => 'az1',
                'access_type' => 'public-igw',
                'cidr' => '@app.next_vpc_subnet_network_5@',
                'id' => null,
                'route-table-id' => null,
            ],
            'public-aws-az2' => [
                'name' => '@app.prefix@-subnet-public-az2',
                'availability_zone' => 'az2',
                'access_type' => 'public-igw',
                'cidr' => '@app.next_vpc_subnet_network_7@',
                'id' => null,
                'route-table-id' => null,
            ],
            'private-awz-az1' => [
                'name' => '@app.prefix@-subnet-private-az1',
                'availability_zone' => 'az1',
                'access_type' => 'main',
                'cidr' => '@app.next_vpc_subnet_network_1@',
                'id' => null,
            ],
            'private-awz-az2' => [
                'name' => '@app.prefix@-subnet-private-az2',
                'availability_zone' => 'az2',
                'access_type' => 'main',
                'cidr' => '@app.next_vpc_subnet_network_3@5',
                'id' => null,
            ],
            'nat-aws-az1' => [
                'name' => '@app.prefix@-subnet-nat-az1',
                'availability_zone' => 'az1',
                'access_type' => 'public-nat',
                'cidr' => '@app.next_vpc_subnet_network_9@',
                'natgw_key' => 'natgw-aws-az1',
                'id' => null,
                'route-table-id' => null,
            ],
            'nat-aws-az2' => [
                'name' => '@app.prefix@-subnet-nat-az2',
                'availability_zone' => 'az2',
                'access_type' => 'public-nat',
                'cidr' => '@app.next_vpc_subnet_network_11@',
                'natgw_key' => 'natgw-aws-az2',
                'id' => null,
                'route-table-id' => null,
            ],
        ],
    ];
