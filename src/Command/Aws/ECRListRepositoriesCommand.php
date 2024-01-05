<?php

namespace Uxmal\Devtools\Command\Aws;

use Aws\Ecr\EcrClient;

class ECRListRepositoriesCommand extends AWSCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'aws:ecr-list-repositories';

    protected $description = 'Lista los repositorios de Amazon ECR';

    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }
        $this->listRepositories();
    }

    public function listRepositories(): void
    {
        $this->info('Listando repositorios en Amazon ECR...');

        $this->info('AWS_ACCESS_KEY_ID: '.$this->awsCredentialsAccessKey);
        $this->info('AWS_SECRET_ACCESS_KEY: '.$this->awsCredentialsSecretKey);
        $this->info('AWS_DEFAULT_REGION: '.$this->awsCredentialsRegion);

        // Inicializar el cliente de ECR con las credenciales del archivo .env
        $client = new EcrClient([
            'version' => 'latest',
            'region' => $this->awsCredentialsRegion, // Usar la región desde .env
            'credentials' => [
                'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
            ],
        ]);

        $result = $client->describeRepositories();

        $repositories = $result->get('repositories');

        $this->info('Repositorios encontrados: '.count($repositories));

        foreach ($repositories as $repository) {
            $this->info($repository['repositoryName']);
        }
    }
}
