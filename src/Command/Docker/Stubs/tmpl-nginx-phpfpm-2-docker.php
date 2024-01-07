<?php

return [
    [
        'containers' => [
            'php-fpm' => [
                'alpineVersion' => '3.19',
                'phpVersion' => '8.2',
                'remoteRepo' => 'uxmaltech',
            ],
            'nginx' => [
                'alpineVersion' => '3.19',
                'nginxVersion' => '1.21',
                'remoteRepo' => 'uxmaltech',
            ]
        ]
    ]
];
