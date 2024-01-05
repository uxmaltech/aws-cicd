<?php

namespace Uxmal\Devtools\Command\Aws;

use Random\RandomException;

class Route53CreateDomainsCommand extends AWSCommand
{
    protected $signature = 'aws:route53:create-domains
                                {--ignore-fqdn :     Ignorar la creación de el dominio FQDN}
                                {--ignore-intranet : Ignorar la creación de el dominio intranet}';

    protected $description = 'Crea los dominios en Route53';

    /**
     * @throws RandomException
     */
    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }

        $this->createDomains();
    }

    public function createDomains(): void
    {
        $clusterFqdnDomainStatus = '<fg=red>FAIL</>';
        $clusterIntranetDomainStatus = '<fg=red>FAIL</>';

        $domainStatus = [
            'fqdn' => false,
            'intranet' => false,
        ];
        if ($this->route53DomainExists($this->clusterFqdnDomain)) {
            $clusterFqdnDomainStatus = '<fg=green>OK</>';
            $domainStatus['fqdn'] = true;
        }

        if ($this->route53DomainExists($this->clusterIntranetDomain)) {
            $clusterIntranetDomainStatus = '<fg=green>OK</>';
            $domainStatus['intranet'] = true;
        }

        $headers = ['tipo', 'dominio', 'status'];
        $users = [
            ['FQDN', $this->clusterFqdnDomain, $clusterFqdnDomainStatus],
            ['Intranet', $this->clusterIntranetDomain, $clusterIntranetDomainStatus],
        ];

        // Mostrar la tabla
        $this->table($headers, $users);

        if (! $this->options('ignore-fqdn') && $domainStatus['fqdn'] === false && $this->confirm("¿Deseas crear el dominio {$this->clusterFqdnDomain}?") === true) {
            if ($this->route53CreatePrivateDomain($this->clusterFqdnDomain)) {
                $this->info("Dominio $this->clusterIntranetDomain creado correctamente");
            }
        }

        if (! $this->options('ignore-intranet') && $domainStatus['intranet'] === false && $this->confirm("¿Deseas crear el dominio {$this->clusterIntranetDomain}?") === true) {
            if ($this->route53CreatePrivateDomain($this->clusterIntranetDomain, true, $this->clusterAwsVpcId)) {
                $this->info("Dominio $this->clusterIntranetDomain creado correctamente");
            }
        }

        $this->newLine(2);

    }
}
