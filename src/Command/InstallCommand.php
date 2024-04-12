<?php

namespace Uxmal\Devtools\Command;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'devtools:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the package.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        system('clear');
        $this->install();
    }

    /**
     * Install the package.
     *
     * @return void
     */
    protected function install()
    {
        $this->info('Creando (los) archivo de configuración...');

        $uxmaltech_features_docker = $this->ask('Dockerizar la aplicacion de laravel', 'yes');
        $uxmaltech_features_aws_ecr_repository_managed = $this->ask('Registrar/Crear el repositorio docker en AWS ECR', 'yes');
        $uxmaltech_features_aws_vpc_managed = $this->ask('Crear la VPC en AWS', 'yes');
        $uxmaltech_features_aws_ecs_managed = $this->ask('Crear el cluster ECS en AWS', 'yes');

        $this->call('devtools:build-uxmaltech-config', [
            '--feature-docker' => $uxmaltech_features_docker,
            '--feature-aws-ecr-repository' => $uxmaltech_features_aws_ecr_repository_managed,
            '--feature-aws-vpc' => $uxmaltech_features_aws_vpc_managed,
            '--feature-aws-ecs' => $uxmaltech_features_aws_ecs_managed,
        ]);

        if ($uxmaltech_features_aws_ecr_repository_managed == 'yes') {
            $this->call('devtools:build-aws-ecr-config', []);
        }

        if ($uxmaltech_features_aws_vpc_managed == 'yes') {
            $this->call('devtools:build-aws-vpc-config', []);
        }

        if ($uxmaltech_features_aws_ecs_managed == 'yes') {
            $this->call('devtools:build-aws-ecs-config', []);
        }

        exit(0);

        // Check if have a protected packages from uxmal.
        $this->checkProtectedPackages();

        $this->call('vendor:publish', [
            '--provider' => 'Uxmal\Devtools\DevtoolsServiceProvider',
            '--tag' => 'aws-cicd-docker',
            '--force' => true,
        ]);
    }
}
