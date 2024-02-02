<?php

namespace Uxmal\Devtools\Command\Uxmaltech;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildAwsEcsConfigCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'devtools:build-aws-ecs-config';

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
        }
        system('clear');
        $this->buildAwsEcsConfig();
    }

    private function buildAwsEcsConfig(): void
    {
        $this->info('Creando (los) archivo de configuración (AWS-ECS)...');

        $this->newLine();

        $app_ecs_cluster_name = $this->ask('Nombre del cluster ECS', 'uxmaltech');
        $app_ecs_task_definition_family = $this->ask('Nombre de la definición de tarea ECS', 'uxmaltech');

        $headers = ['Variable', 'Valor'];
        $variablesTable = [
            ['ecs_cluster_name', $app_ecs_cluster_name],
            ['ecs_task_definition_family', $app_ecs_task_definition_family],
        ];

        $this->table($headers, $variablesTable);
        if (! $this->confirm('¿Es correcto?')) {
            $this->error('Abortando...');
            exit(1);
        }
    }
}
