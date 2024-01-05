<?php

namespace Uxmal\Devtools\Traits\AWS;

use Aws\Ecr\EcrClient;
use Aws\Exception\AwsException;
use Aws\Result;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait ECRUtils
{
    private EcrClient $ECRClient;

    public function ecrCreateRepository(string $repositoryName): bool|Result
    {
        if (! isset($this->ECRClient)) {
            $this->initECRClient();
        }
        try {
            return $this->ECRClient->createRepository([
                'repositoryName' => $repositoryName,
            ]);
        } catch (\Exception $e) {
            $this->error('Error al crear el repositorio: '.$e->getMessage());
        }

        return false;
    }

    public function ecrListRepositories(): array
    {
        if (! isset($this->ECRClient)) {
            $this->initECRClient();
        }
        $repo2Return = [];

        try {
            $result = $this->ECRClient->describeRepositories();

            $repositories = $result->get('repositories');

            foreach ($repositories as $repository) {
                $repo2Return[] = (array) $repository;
            }

            return $repo2Return;
        } catch (\Exception $e) {
            $this->error('Error al obtener los repositorios: '.$e->getMessage());
        }

        return $repo2Return;
    }

    public function ecrImageExists(string $repositoryName, string $imageTag): bool
    {

        if (! isset($this->ECRClient)) {
            $this->initECRClient();
        }

        try {
            // Describe the images in the repository
            if (str_contains($repositoryName, '/')) {
                $repositoryNameParts = explode('/', $repositoryName);
                $repositoryName = $repositoryNameParts[1];
            }

            $result = $this->ECRClient->describeImages([
                'repositoryName' => $repositoryName,
                'imageIds' => [
                    [
                        'imageTag' => $imageTag,
                    ],
                ],
            ]);

            return ! empty($result['imageDetails']);
        } catch (AwsException $e) {
            return false;
        }
    }

    private function initECRClient(): void
    {
        if (empty($this->awsCredentialsRegion) || empty($this->awsCredentialsAccessKey) || empty($this->awsCredentialsSecretKey)) {
            $this->error('No se han definido las credenciales de AWS en el archivo .env');
            exit(1);
        }
        $sessionToken = [];
        if (! empty($this->awsCredentialsToken)) {
            $sessionToken = ['token' => $this->awsCredentialsToken];
        }
        $this->ECRClient = new EcrClient([
            'version' => 'latest',
            'region' => $this->awsCredentialsRegion, // Usar la región desde .env
            'credentials' => [
                'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
            ] + $sessionToken,
        ]);
    }

    public function dockerLoginToECR(): void
    {
        if (! isset($this->ECRClient)) {
            $this->initECRClient();
        }
        try {
            $result = $this->ECRClient->getAuthorizationToken();
            $authorizationData = $result->get('authorizationData')[0];
            $token = $authorizationData['authorizationToken'];
            $token = base64_decode($token);
            $token = explode(':', $token);
            $username = $token[0];
            $password = $token[1];
            $endpoint = $authorizationData['proxyEndpoint'];

            if ($this->devMode) {
                $this->info("Login en ECR...[docker login --username $username --password-stdin $endpoint]");
            }

            $command = sprintf('echo %s | docker login --username %s --password-stdin %s',
                escapeshellarg($password),
                escapeshellarg($username),
                escapeshellarg($endpoint));

            $process = Process::fromShellCommandline($command);

            $process->mustRun();
            $this->info('Login OK!!');
        } catch (ProcessFailedException $exception) {
            // Handle the error
            echo 'Docker login failed: '.$exception->getMessage();
        } catch (\Exception $e) {
            $this->error('Error al hacer login en ECR: '.$e->getMessage());
        }
    }
}
