<?php

namespace Uxmal\Devtools\Traits\AWS;

use Aws\Result;
use Aws\Route53\Route53Client;

trait Route53Utils
{
    private Route53Client $Route53Client;

    private Result $route53ListHostedZonesResponse;

    private function initRoute53Client(): void
    {
        if (! isset($this->Route53Client)) {
            $sessionToken = [];
            if (! empty($this->awsCredentialsToken)) {
                $sessionToken = ['token' => $this->awsCredentialsToken];
            }
            $this->Route53Client = new Route53Client([
                'credentials' => [
                    'key' => $this->awsCredentialsAccessKey,
                    'secret' => $this->awsCredentialsSecretKey,
                ] + $sessionToken,
                'region' => $this->awsCredentialsRegion,
                'version' => '2013-04-01',
            ]);
        }
    }

    public function route53GetDomains(): array
    {
        $this->initECSClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        return $this->route53ListHostedZones()->toArray()['HostedZones'];
    }

    public function route53DomainExists(string $domainToCheck): bool
    {
        $this->initECSClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        $domains = $this->route53ListHostedZones()->toArray()['HostedZones'];
        foreach ($domains as $domain) {
            if ($domain['Name'] === $domainToCheck.'.') {
                return $domain;
            }
        }

        return false;
    }

    public function route53ListHostedZones(): array|Result
    {
        $this->initECSClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        if (isset($this->route53ListHostedZonesResponse)) {
            return $this->route53ListHostedZonesResponse;
        }
        if (! isset($this->Route53Client)) {
            $this->initRoute53Client();
        }
        try {
            $this->route53ListHostedZonesResponse = $this->Route53Client->listHostedZones();

            return $this->route53ListHostedZonesResponse;
        } catch (\Exception $e) {
            $this->error('Error al obtener los dominios: '.$e->getMessage());
        }

        return [];
    }

    protected function route53CreateDomain($domain_name, $private = false, $vpcId = null)
    {
        $this->initECSClient();
        $isDryRun = $this->option('dry-run') !== 'false';

        $domain_name = $domain_name.'.';
        $params = [
            'Name' => $domain_name,
            'CallerReference' => uniqid(),
            'HostedZoneConfig' => [
                'Comment' => 'Created by Uxmal Devtools',
            ],
        ];
        if ($private === true) {
            if ($vpcId === null) {
                $this->error('No se ha encontrado el VPC ID, se necesita definirlo en el archivo .env [DEVTOOLS_AWS_VPC_ID]');
                exit(1);
            }
            $params['VPC'] = [
                'VPCId' => $vpcId,
                'VPCRegion' => $this->awsCredentialsRegion,
            ];
            $params['HostedZoneConfig']['PrivateZone'] = true;
        }
        try {
            $return = $this->Route53Client->createHostedZone($params);

            return $return['HostedZone']['Id'];
        } catch (\Exception $e) {
            $this->error("Error al crear el dominio $domain_name: ".$e->getMessage());
        }

        return [];
    }
}
