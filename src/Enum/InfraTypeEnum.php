<?php

namespace Uxmal\Devtools\Enum;

enum InfraTypeEnum: string
{
    case nginx_phpfpm_2 = 'Nginx+Php-Fpm (2 services)';
    case apache_php = 'Apache+Php (1 service)';
    case artisan_php = 'Artisan+Php (1 service)';

}
