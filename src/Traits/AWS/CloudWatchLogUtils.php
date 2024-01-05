<?php

namespace Uxmal\Devtools\Traits\AWS;

use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Exception\AwsException;

trait CloudWatchLogUtils
{
    protected CloudWatchLogsClient $CloudWatchLogClient;

    protected function initCloudWatchLogClient(): void
    {
        if (empty($this->awsCredentialsRegion) || empty($this->awsCredentialsAccessKey) || empty($this->awsCredentialsSecretKey)) {
            $this->error('No se han definido las credenciales de AWS en el archivo .env');
            exit(1);
        }

        if (! isset($this->CloudWatchLogClient)) {
            $sessionToken = [];
            if (! empty($this->awsCredentialsToken)) {
                $sessionToken = ['token' => $this->awsCredentialsToken];
            }
            $this->CloudWatchLogClient = new CloudWatchLogsClient([
                'version' => 'latest',
                'region' => $this->awsCredentialsRegion, // Usar la región desde .env
                'credentials' => [
                    'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                    'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
                ] + $sessionToken,
            ]);
        }
    }

    public function createCWLogGroup($logGroupName): string
    {
        $this->initCloudWatchLogClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->CloudWatchLogClient->createLogGroup([
                'logGroupName' => $logGroupName,
            ]);
            dump($result);

            return true;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createCWLogGroup] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }
}
