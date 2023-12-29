<?php

namespace Uxmal\AwsCICD\Command\Aws;

use Aws\Ecr\EcrClient;
use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Uxmal\AwsCICD\Traits\ProcessUtils;

class ECRCreateRepositoryCommand extends Command
{
    use ProcessUtils;


    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'aws:ecr-create-repository {repository_name}';

    protected $description = 'Crea un nuevo repositorio en Amazon ECR';

    public function handle(): void
    {
        $repositoryName = $this->argument('name');

        // Inicializar el cliente de ECR con las credenciales del archivo .env
        $client = new EcrClient([
            'version' => 'latest',
            'region'  => config('AWS_DEFAULT_REGION'), // Usar la región desde .env
            'credentials' => [
                'key'    => config('AWS_ACCESS_KEY_ID'), // Access key desde .env
                'secret' => config('AWS_SECRET_ACCESS_KEY'), // Secret key desde .env
            ],
        ]);

        try {
            $result = $client->createRepository([
                'repositoryName' => $repositoryName,
            ]);

            $this->info("Repositorio creado: " . $result['repository']['repositoryUri']);
        } catch (\Exception $e) {
            $this->error("Error al crear el repositorio: " . $e->getMessage());
        }
    }

}
