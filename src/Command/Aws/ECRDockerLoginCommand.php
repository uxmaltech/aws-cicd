<?php

namespace Uxmal\Devtools\Command\Aws;

use Aws\Ecr\EcrClient;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ECRDockerLoginCommand extends AWSCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'aws:ecr-login-docker';

    protected $description = 'Logea Docker en Amazon ECR';

    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }
        $this->loginToDocker();
    }

    public function loginToDocker(): void
    {
        $this->info('Logeando Docker en Amazon ECR...');

        // Inicializar el cliente de ECR con las credenciales del archivo .env
        $client = new EcrClient([
            'version' => 'latest',
            'region' => $this->awsCredentialsRegion, // Usar la región desde .env
            'credentials' => [
                'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
            ],
        ]);

        $result = $client->getAuthorizationToken();

        $authToken = base64_decode($result['authorizationData'][0]['authorizationToken']);
        [$username, $password] = explode(':', $authToken);

        // The Docker login command (to be executed on your system shell)
        $cmd = "docker login -u {$username} -p {$password} {$result['authorizationData'][0]['proxyEndpoint']}";
        try {
            if ($this->debugMode) {
                $this->warn("Running Command [bash -c '$cmd']");
            }
            $this->runCmd(['bash', '-c', $cmd]);
        } catch (ProcessFailedException $exception) {
            $this->warn('An error occurred: '.$exception->getMessage());
        }
    }
}
