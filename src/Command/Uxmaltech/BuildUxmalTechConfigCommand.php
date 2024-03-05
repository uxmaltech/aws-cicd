<?php

namespace Uxmal\Devtools\Command\Uxmaltech;

use Illuminate\Console\Command;
use Random\RandomException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Enum\InfraTypeEnum;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildUxmalTechConfigCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     *
     * --build
     * --run
     * --check
     * ? --push
     * ? --pull
     * ? --tag=latest
     * ? --tag=dev
     * ? --tag=prod
     * ? --tag=staging
     * ? --tag=testing
     * ? --tag=qa
     * ? --push-ecr=tag
     */
    protected $signature = 'devtools:build-uxmaltech-config
                                                    {--feature-docker : Dockerizar la aplicación de laravel}
                                                    {--feature-aws-ecr-repository : Registrar/Crear el repositorio docker en AWS ECR}
                                                    {--feature-aws-vpc : Crear la VPC en AWS}
                                                    {--feature-aws-ecs : Crear el cluster ECS en AWS}';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    public function handle(): void
    {
        try {
            if ($this->checkEnvironment() === false) {
                exit(1);
            }
        } catch (ProcessFailedException $exception) {
            $this->warn('An error occurred: '.$exception->getMessage());
        } catch (RandomException $e) {
            $this->warn('An error occurred: '.$e->getMessage());
        }
        system('clear');
        $this->buildUxmalTech();
    }

    private function buildUxmalTech(): void
    {
        $this->info('Creando (los) archivo de configuración (UxmalTech)...');

        $options = InfraTypeEnum::cases();
        $app_mode_type = $this->choice(
            'Escoje la estrategia de dockerización para tu applicación de laravel',
            array_map(fn ($option) => $option->value, $options),
            $defaultIndex = 0);

        switch ($app_mode_type) {
            case InfraTypeEnum::nginx_phpfpm_2->value:
                $app_mode_template = __DIR__.'/Stubs/tmpl-nginx-php-fpm.php';
                $docker_template = __DIR__.'/Stubs/tmpl-nginx-phpfpm-2-docker.php';
                $app_mode = 'nginx-phpfpm-2';
                $service_ports = config('uxmaltech.service_ports', ['http' => 80, 'https' => '443']);
                break;
            case InfraTypeEnum::apache_php->value:
                $app_mode_template = __DIR__.'/Stubs/tmpl-apache-php.php';
                $docker_template = __DIR__.'/Stubs/tmpl-apache-php-docker.php';
                $app_mode = 'apache-php';
                $service_ports = config('uxmaltech.service_ports', ['http' => 80, 'https' => '443']);
                break;
            case InfraTypeEnum::artisan_php->value:
                $app_mode_template = __DIR__.'/Stubs/tmpl-artisan-php.php';
                $docker_template = __DIR__.'/Stubs/tmpl-artisan-php-fpm-docker.php';
                $app_mode = 'artisan-php';
                $service_ports = config('uxmaltech.service_ports', []);
                break;
            default:
                $this->error('Opción no implementada');
                exit(1);
        }

        $this->line('<info>Creando configuración para</info> [<comment>'.$app_mode_type.'</comment>]:');
        $this->newLine();

        $app_name = $this->ask('Nombre de la aplicación', config('uxmaltech.name', config('app.name')));
        $app_domain = $this->ask('Dominio de la aplicación', config('uxmaltech.domain', parse_url(config('app.url'), PHP_URL_HOST)));
        $app_subdomain = $this->ask('Subdominio de la aplicación', config('uxmaltech.subdomain', config('app.name')));
        $app_internal_domain = str_replace('.', '-', $app_domain).'.intranet';

        $app_prefix = $this->ask('Prefijo de la aplicación', config('uxmaltech.prefix', 'uxdt'));
        $app_key = $this->ask('Llave de la aplicación', config('uxmaltech.key', config('app.key')));

        $app_feature_docker = $this->option('feature-docker');
        $app_feature_aws_ecr_repository = $this->option('feature-aws-ecr-repository');
        $app_feature_aws_vpc = $this->option('feature-aws-vpc');
        $app_feature_aws_ecs = $this->option('feature-aws-ecs');

        $headers = ['Variable', 'Valor'];
        $variablesTable = [
            ['name', $app_name],
            ['prefix', $app_prefix],
            ['key', $app_key],
            ['domain', $app_domain],
            ['subdomain', $app_subdomain],
            ['internal.domain', $app_internal_domain],
            ['features.docker', $app_feature_docker ? 'yes' : 'no'],
            ['features.aws.ecr.repository', $app_feature_aws_ecr_repository ? 'yes' : 'no'],
            ['features.aws.vpc', $app_feature_aws_vpc ? 'yes' : 'no'],
            ['features.aws.ecs', $app_feature_aws_ecs ? 'yes' : 'no'],
        ];

        $this->table($headers, $variablesTable);
        if (! $this->confirm('¿Es correcto?')) {
            $this->error('Abortando...');
            exit(1);
        }

        $search = [
            '@app.name@',
            '@app.prefix@',
            '@app.key@',
            '@app.domain@',
            '@app.subdomain@',
            '@app.internal_domain@',
            '@app.mode@',
            '@app.service.ports@',
            '@dockerized@',
            '@aws.ecr.repository.managed@',
            '@aws.vpc.managed@',
            '@aws.ecs.managed@',
        ];

        $replace = [
            $app_name,
            $app_prefix,
            $app_key,
            $app_domain,
            $app_subdomain,
            $app_internal_domain,
            $app_mode,
            $this->var_export_short($service_ports, true),
            $app_feature_docker ? 'true' : 'false',
            $app_feature_aws_ecr_repository ? 'true' : 'false',
            $app_feature_aws_vpc ? 'true' : 'false',
            $app_feature_aws_ecs ? 'true' : 'false',
        ];

        $template = __DIR__ . '/Stubs/tmpl-uxmaltech.stub';

        $config = file_get_contents($template);
        $config = str_replace($search, $replace, $config);

        file_put_contents(config_path('uxmaltech.php'), $config);

        system(base_path('./vendor/bin/pint').' '.config_path('uxmaltech.php'));
    }
}
