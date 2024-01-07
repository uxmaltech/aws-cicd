<?php

namespace Uxmal\Devtools\Command;

use Random\RandomException;
use Uxmal\Devtools\Enum\InfraTypeEnum;

class BuildInfrastructureConfigCommand extends Aws\AWSCommand
{
    protected $signature = 'devtools:build-infrastructure-config';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    /**
     * @throws RandomException
     */
    public function handle(): void
    {
        // ... existing setup code ...
        if ($this->checkEnvironment() === false) {
            exit(1);
        }

        $this->buildConfig();
    }

    private function buildConfig(): void
    {
        $this->info('Creando el archivo de configuración de infraestructura...');

        $options = InfraTypeEnum::cases();
        $choice = $this->choice(
            'Escoje la infraestructura deseada',
            array_map(fn ($option) => $option->value, $options),
            $defaultIndex = 0);

        switch ($choice) {
            case InfraTypeEnum::nginx_phpfpm_2->value:
                $infra_template = __DIR__.'/Aws/Stubs/tmpl-nginx-php-fpm.php';
                break;
            case InfraTypeEnum::apache_php->value:
                $infra_template = __DIR__.'/Aws/Stubs/tmpl-apache-php.php';
                break;
            case InfraTypeEnum::artisan_php->value:
                $infra_template = __DIR__.'/Aws/Stubs/tmpl-artisan-php.php';
                break;
            default:
                $this->error('Opción no válida');
                exit(1);
        }

        $this->info('Creando configuración para '.$choice.'...');
        $this->newLine();

        $app_name = $this->ask('Nombre de la aplicación', 'laravel');
        $app_domain = $this->ask('Dominio de la aplicación (default:ninguno)', null);
        $app_internal_domain = str_replace('.', '-', $app_domain).'.intranet';
        $app_subdomain = $this->ask('Subdominio de la aplicación (default:ninguno)', null);

        $app_prefix = $this->ask('Prefijo de la aplicación (uxdt)', 'uxdt');
        $app_key = $this->ask('Llave de la aplicación', config('app.key'));
        $app_vpc_network = $this->ask('Red de la VPC', '192.168.0.0');
        $app_vpc_netmask = $this->ask('Máscara de red de la VPC', 20);
        $app_vpc_subnet_netmask = $this->ask('Máscara de red de la subredes de la VPC', 25);

        $ecr_nginx_image = $this->ask('Imagen de nginx', 'latest');
        $ecr_nginx_tag = $this->ask('Tag de la imagen de nginx', 'latest');
        $ecr_php_fpm_image = $this->ask('Imagen de php-fpm', 'latest');
        $ecr_php_fpm_tag = $this->ask('Tag de la imagen de php-fpm', 'latest');

        $headers = ['Variable', 'Valor'];
        $variablesTable = [
            ['name', $app_name],
            ['prefix', $app_prefix],
            ['key', $app_key],
            ['domain', $app_domain],
            ['subdomain', $app_subdomain],
            ['internal.domain', $app_internal_domain],
            ['vpc_network', $app_vpc_network],
            ['vpc_netmask', $app_vpc_netmask],
            ['vpc_subnet_netmask', $app_vpc_subnet_netmask],
            ['ecr_nginx_image', $ecr_nginx_image],
            ['ecr_nginx_tag', $ecr_nginx_tag],
            ['ecr_php_fpm_image', $ecr_php_fpm_image],
            ['ecr_php_fpm_tag', $ecr_php_fpm_tag],
        ];
        $this->table($headers, $variablesTable);
        if (! $this->confirm('¿Es correcto?')) {
            $this->error('Abortando...');
            exit(1);
        }

        $this->info('Creando archivo de configuración... config/aws-infra.php');

        $search = [
            '@app.name@',
            '@app.prefix@',
            '@app.key@',
            '@app.domain@',
            '@app.subdomain@',
            '@app.internal_domain@',
            '@app.vpc_network@',
            '@app.vpc_netmask@',
            '@app.vpc_subnet_netmask@',
            '@ecr.nginx.image@',
            '@ecr.nginx.tag@',
            '@ecr.php-fpm.image@',
            '@ecr.php-fpm.tag@',
        ];

        $replace = [
            $app_name,
            $app_prefix,
            $app_key,
            $app_domain,
            $app_subdomain,
            $app_internal_domain,
            $app_vpc_network,
            $app_vpc_netmask,
            $app_vpc_subnet_netmask,
            $ecr_nginx_image,
            $ecr_nginx_tag,
            $ecr_php_fpm_image,
            $ecr_php_fpm_tag,
        ];

        $subnets = $this->generateSubnets($app_vpc_network, $app_vpc_netmask, $app_vpc_subnet_netmask);
        foreach ($subnets as $index => $subnet) {
            $search[] = '@app.next_vpc_subnet_network_'.($index + 1).'@';
            $replace[] = $subnet;
        }

        $config = file_get_contents($infra_template);
        $config = str_replace($search, $replace, $config);

        $this->info('Escribiendo archivo de configuración... config/aws-infra.php');
        if (file_exists(config_path('aws-infra.php'))) {
            copy(config_path('aws-infra.php'), config_path('aws-infra.php.bak'.date('YmdHis')));
            unlink(config_path('aws-infra.php'));
        }

        file_put_contents(config_path('aws-infra.php'), $config);

        $this->info('Archivo de configuración creado... config/aws-infra.php');

    }
}
