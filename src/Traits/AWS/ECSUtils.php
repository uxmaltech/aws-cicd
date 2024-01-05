<?php

namespace Uxmal\Devtools\Traits\AWS;

use Aws\Ecs\EcsClient;
use Aws\Exception\AwsException;
use Aws\Result;

trait ECSUtils
{
    protected EcsClient $EcsClient;

    protected function initECSClient(): void
    {
        if (empty($this->awsCredentialsRegion) || empty($this->awsCredentialsAccessKey) || empty($this->awsCredentialsSecretKey)) {
            $this->error('No se han definido las credenciales de AWS en el archivo .env');
            exit(1);
        }

        $sessionToken = [];
        if (! empty($this->awsCredentialsToken)) {
            $sessionToken = ['token' => $this->awsCredentialsToken];
        }
        if (! isset($this->EcsClient)) {
            $this->EcsClient = new EcsClient([
                'version' => 'latest',
                'region' => $this->awsCredentialsRegion, // Usar la región desde .env
                'credentials' => [
                    'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                    'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
                ] + $sessionToken,
            ]);
        }

    }

    public function ecsCreateCluster($clusterName): false|string
    {
        $this->initECSClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->EcsClient->createCluster([
                'clusterName' => $clusterName,
            ]);

            return $result['cluster']['clusterArn'];
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createECSCluster] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function ecsClusterExists($clusterArn): bool
    {
        $this->initECSClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->EcsClient->describeClusters([
                'clusters' => [$clusterArn],
            ]);

            // Check if the ECS cluster exists
            foreach ($result['clusters'] as $cluster) {
                if ($cluster['clusterArn'] === $clusterArn && $cluster['status'] !== 'INACTIVE') {
                    return true;
                }
            }

            return false; // Cluster not found or inactive
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createECSCluster] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    /**
     * Creates an ECS task definition from a JSON file.
     *
     * @param  string  $jsonFilePath Path to the JSON file containing the task definition.
     * @return string A message indicating the result of the operation.
     */
    public function createTaskDefinition($jsonString): string
    {
        try {
            // Load and decode the JSON file
            $taskDefinition = json_decode($jsonString, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: '.json_last_error_msg());
            }

            // Register the task definition
            $result = $this->ecsClient->registerTaskDefinition($taskDefinition);

            return 'Task definition created successfully. ARN: '.$result['taskDefinition']['taskDefinitionArn'];
        } catch (AwsException $e) {
            return 'Error creating task definition: '.$e->getMessage();
        } catch (\Exception $e) {
            return 'Error: '.$e->getMessage();
        }
    }
}
