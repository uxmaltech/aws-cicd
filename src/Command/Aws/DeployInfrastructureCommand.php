<?php

namespace Uxmal\Devtools\Command\Aws;

use Random\RandomException;
use Symfony\Component\Console\Helper\TableSeparator;

class DeployInfrastructureCommand extends AWSCommand
{
    protected $signature = 'aws:deploy-infrastructure 
                                {--aws-access-key=}
                                {--aws-access-secret=}
                                {--aws-region=}
                                {--aws-token=}';

    protected $description = 'Crea los dominios en Route53';

    private string $routeTableMainName = 'main';

    private string $routeTablePublicIGWName = 'public-igw';

    private string $routeTablePublicNatName = 'public-nat';

    // ECS
    private null|false|string $ecsClusterArn;

    private ?string $ecsClusterName;

    //private null|string $ecsClusterName;
    private ?string $externalDomainName;

    private ?string $internalDomainName;

    private ?string $externalDomainId;

    private ?string $internalDomainId;
    /***** UP REFACTORING*****/

    /***** DOWN OK*****/
    private string $vpcConfigFile;

    private ?string $app_name;

    private ?string $app_prefix;

    private ?string $app_key;

    private ?string $app_host;

    private ?string $vpc_id;

    private ?string $vpc_cidr;

    private ?string $vpc_name;

    private ?string $vpc_network;

    private ?string $vpc_netmask;

    private ?string $vpc_subnet_netmask;

    private ?string $vpc_igw_id;

    private ?string $vpc_igw_name;

    private ?array $vpc_nat_gateways;

    private ?array $vpc_subnets;

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
        $this->configureAWSDryRun();
    }

    /**
     * @throws RandomException
     */
    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }
        system('clear');
        $this->createInfra();
    }

    public function createInfra(): void
    {

        $this->info('Creando la infraestructura...');
        if ($this->option('aws-access-key')) {
            $this->awsCredentialsAccessKey = $this->option('aws-access-key');
        }

        if ($this->option('aws-access-secret')) {
            $this->awsCredentialsSecretKey = $this->option('aws-access-secret');
        }

        if ($this->option('aws-region')) {
            $this->awsCredentialsRegion = $this->option('aws-region');
        }

        if ($this->option('aws-token')) {
            $this->awsCredentialsToken = $this->option('aws-token');
        }

        if (empty($this->awsCredentialsAccessKey) || empty($this->awsCredentialsSecretKey) || empty($this->awsCredentialsRegion)) {
            $this->error('No se han especificado las credenciales de AWS');
            exit(1);
        }

        // Environment Data
        $this->newLine();
        $this->vpcConfigFile = config_path('aws-vpc.php');
        $this->app_name = config('app.name');
        $this->app_prefix = config('uxmaltech.prefix');
        $this->app_key = config('uxmaltech.key');
        $this->app_host = config('uxmaltech.subdomain').'.'.config('uxmaltech.domain');

        $this->vpc_id = config('aws-vpc.id', '<commment>empty</comment>');
        $this->vpc_cidr = config('aws-vpc.cidr');
        $this->vpc_name = config('aws-vpc.name');
        $this->vpc_network = explode('/', $this->vpc_cidr)[0];
        $this->vpc_netmask = explode('/', $this->vpc_cidr)[1];
        $this->vpc_subnet_netmask = config('aws-vpc.subnet_netmask');
        $this->vpc_igw_id = config('aws-vpc.internet_gateway.id', '<commment>empty</comment>');
        $this->vpc_igw_name = config('aws-vpc.internet_gateway.name');

        $this->vpc_nat_gateways = config('aws-vpc.nat_gateways');
        $tableNatGw = [new TableSeparator()];
        if (! empty($this->vpc_nat_gateways)) {
            foreach ($this->vpc_nat_gateways as $natgw_name => $natgw_data) {
                $this->vpc_nat_gateways[$natgw_name]['id'] = config('aws-vpc.nat_gateways.'.$natgw_name.'.id');
                $this->vpc_nat_gateways[$natgw_name]['elastic_ip'] = config('aws-vpc.nat_gateways.'.$natgw_name.'.elastic_ip');
                $tableNatGw[] = ['VPC Nat GW '.$natgw_name.' ID', $this->vpc_nat_gateways[$natgw_name]['id'] ?? '<comment>empty</comment>'];
                $tableNatGw[] = ['VPC Nat GW '.$natgw_name.' Elastic IP', $this->vpc_nat_gateways[$natgw_name]['elastic_ip'] ?? '<comment>empty</comment>'];
            }
        }

        $this->vpc_subnets = config('aws-vpc.subnets');
        $tableSubnets = [];
        if (! empty($this->vpc_subnets)) {
            foreach ($this->vpc_subnets as $subnet_name => $subnet_data) {
                $this->vpc_subnets[$subnet_name]['id'] = config('aws-vpc.subnets.'.$subnet_name.'.id');
                $this->vpc_subnets[$subnet_name]['route-table-id'] = config('aws-vpc.subnets.'.$subnet_name.'.route-table-id');
                $tableSubnets[] = new TableSeparator();
                $tableSubnets[] = ['VPC Subnet '.$subnet_name.' ID', $this->vpc_subnets[$subnet_name]['id'] ?? '<comment>empty</comment>'];
                $tableSubnets[] = ['VPC Subnet '.$subnet_name.' Route Table ID', $this->vpc_subnets[$subnet_name]['route-table-id'] ?? '<comment>empty</comment>'];
                $tableSubnets[] = ['VPC Subnet '.$subnet_name.' Extra',
                    ($this->vpc_subnets[$subnet_name]['cidr'] ?? '<comment>empty</comment>').'|'.
                    ($this->vpc_subnets[$subnet_name]['availability_zone'] ?? '<comment>empty</comment>').'|'.
                    ($this->vpc_subnets[$subnet_name]['access_type'] ?? '<comment>empty</comment>'),
                ];

            }
        }

        $headers = ['Variable', 'Valor'];
        $envTable = [
            ['App Name', $this->app_name],
            ['App Prefix', $this->app_prefix], //uxmaltech.prefix
            ['App Key', $this->app_key],
            ['App Host', $this->app_host],
            new TableSeparator(),
            ['VPC ID', $this->vpc_id],
            ['VPC Name', $this->vpc_name],
            ['VPC CIDR', $this->vpc_cidr],
            ['VPC Network', $this->vpc_network],
            ['VPC Netmask', $this->vpc_netmask],
            ['VPC Subnet Netmask', $this->vpc_subnet_netmask],
            ['VPC Internet Gateway ID', $this->vpc_igw_id],
            ['VPC Internet Gateway Name', $this->vpc_igw_name],
        ];
        $this->table($headers, array_merge($envTable, $tableNatGw, $tableSubnets));

        if (! $this->confirm('¿Es correcto, proceder a la creación de la VPC?')) {
            $this->error('Abortando...');
            exit(1);
        }

        if ($this->isDryRun()) {
            $this->info('Dry run, no se creará nada en AWS');
            exit(0);
        }
        $this->newLine();

        // Create/Check VPC
        $this->processVpc();

        // Create/Check Internet Gateway
        $this->processIGW();

        // Create/Check RouteTables
        $this->processSubnetsAndRouteTables();

        return;  /// WIP (Domains and Clusters)

        // Create/Check Domains
        $this->processDomains();

        // Create/Check ECS Cluster
        $this->processECSCluster();

        // Create/Check ECS Task Definitions

    }

    private function processNATGW(string $natgw_key, array $natgw_data): void
    {
        /**
         * "name" => "pslzlo-natgw-az1"
         * "subnet_key" => "public-awz-az1"
         * "elastic_ip" => null
         * "id" => null
         */
        if (! empty($natgw_data['elastic_ip']) && ! $this->elasticIpExists($natgw_data['elastic_ip'])) {
            $this->warn('Elastic IP found but not exists in AWS, creating...');
            $natgw_data['elastic_ip'] = null;
        }

        if (empty($natgw_data['elastic_ip'])) {
            $natgw_data['elastic_ip'] = $this->createEipAlloc('elip-'.$natgw_key);
            $this->info('Waiting for Elastic IP to be available...');
            $this->waitUntilTrue(function () use ($natgw_data) {
                return $this->elasticIpExists($natgw_data['elastic_ip']);
            });
            $this->replaceConfigKey('nat_gateways.'.$natgw_key.'.elastic_ip', $natgw_data['elastic_ip'], true, $this->vpcConfigFile);
        }

        $this->info('Elastic IP For NatGateway '.$natgw_key.' '.$natgw_data['elastic_ip']."\t\t\t".'[<comment>OK</comment>]');

        if (! empty($natgw_data['id'])) {
            if (! $this->internetNatGatewayExists($natgw_data['id'])) {
                $this->warn('NAT Gateway found, but not in AWS, creating ['.$natgw_data['id'].']');
                $natgw_data['id'] = null;
            }
        }

        if (empty($natgw_data['id'])) {
            $subnet_to_attach_data = $this->vpc_subnets[$natgw_data['subnet_key']];

            if (empty($subnet_to_attach_data['id']) || ! $this->subnetExists($subnet_to_attach_data['id'])) {
                $this->error('Public Subnet not found, please check your VPC configuration ');
                exit(1);
            }

            $natgw_data['id'] = $this->createNatGateway($subnet_to_attach_data['id'], $natgw_data['elastic_ip'], $natgw_key);
            $this->warn('Waiting for NAT Gateway to be available...');
            $this->waitUntilTrue(function () use ($natgw_data) {
                $this->output->write('.');

                return $this->internetNatGatewayExists($natgw_data['id']);
            });
            $this->newLine();
            $this->replaceConfigKey('nat_gateways.'.$natgw_key.'.id', $natgw_data['id'], true, $this->vpcConfigFile);
        }

        $this->info('NAT Gateway ['.$natgw_data['id'].']'."\t\t\t".'[<comment>OK</comment>]');

    }

    private function processSubnetPublic(string $subnet_key, array $subnet_data): void
    {
        /**
         * "name" => "pslzlo-subnet-public-az1"
         * "availability_zone" => "az1"
         * "access_type" => "public-igw"
         * "cidr" => "10.12.1.0/24"
         * "id" => null
         * "route-table-id" => null
         */
        $availability_zones = $this->getAvailabilityZones();
        if (! empty($subnet_data['id']) && ! $this->subnetExists($subnet_data['id'])) {
            $this->warn('Subnet found, but not in AWS, creating new subnet [<comment>'.$subnet_data['name'].']</comment>');
            $subnet_data['id'] = null;
        }

        if (empty($subnet_data['id'])) {
            $subnet_data['id'] = $this->createSubnet(
                $this->vpc_id,
                $subnet_data['cidr'],
                $subnet_data['name'],
                $availability_zones[$subnet_data['availability_zone']]
            )['Subnet']['SubnetId'];
            $this->waitUntilTrue(function () use ($subnet_data) {
                return $this->subnetExists($subnet_data['id']);
            });
            $this->replaceConfigKey('subnets.'.$subnet_key.'.id', $subnet_data['id'], true, $this->vpcConfigFile);
        }

        if (! empty($subnet_data['route-table-id'])) {
            if (! $this->routeTableExists($subnet_data['route-table-id'])) {
                $this->warn('Route Table found, but not in AWS, creating new route table');
                $subnet_data['route-table-id'] = null;
            } else {
                if (! $this->isRoutePresentInRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $this->vpc_igw_id)) {
                    $this->addRouteToRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $this->vpc_igw_id);
                    $this->waitUntilTrue(function () use ($subnet_data) {
                        return $this->isRoutePresentInRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $this->vpc_igw_id);
                    });
                }
            }
        }

        if (empty($subnet_data['route-table-id'])) {
            $subnet_data['route-table-id'] = $this->createRouteTable($this->vpc_id, 'route-table-'.$subnet_key);
            $this->waitUntilTrue(function () use ($subnet_data) {
                return $this->routeTableExists($subnet_data['route-table-id']);
            });
            $this->addRouteToRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $this->vpc_igw_id);
            $this->waitUntilTrue(function () use ($subnet_data) {
                return $this->isRoutePresentInRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $this->vpc_igw_id);
            });
            $this->setRouteTableIdToSubnet($subnet_data['id'], $subnet_data['route-table-id']);
            $this->waitUntilTrue(function () use ($subnet_data) {
                return $this->getSubnetAssociationKey($subnet_data['id'], 'RouteTableId') === $subnet_data['route-table-id'];
            });
            $this->replaceConfigKey('subnets.'.$subnet_key.'.route-table-id', $subnet_data['route-table-id'], true, $this->vpcConfigFile);

        }
        $this->info('VPC (Public) Subnet '.$subnet_data['id'].': '."\t\t\t".'[<comment>OK</comment>]');
    }

    private function processSubnetPublicNat(string $subnet_key, array $subnet_data): void
    {
        /**
         * 'availability_zone' => 'az1',
         * 'access_type' => 'public-nat',
         * 'cidr' => '10.12.4.0/24',
         * 'natgw_key' => 'natgw-aws-az1',
         * 'id' => null,
         * 'route-table-id' => null,
         */
        $availability_zones = $this->getAvailabilityZones();
        if (! empty($subnet_data['id']) && ! $this->subnetExists($subnet_data['id'])) {
            $this->warn('Subnet found, but not in AWS, creating new subnet [<comment>'.$subnet_data['name'].']</comment>');
            $subnet_data['id'] = null;
        }

        if (empty($subnet_data['id'])) {
            $subnet_data['id'] = $this->createSubnet(
                $this->vpc_id,
                $subnet_data['cidr'],
                $subnet_data['name'],
                $availability_zones[$subnet_data['availability_zone']]
            )['Subnet']['SubnetId'];
            $this->info('Waiting for Subnet to be available...');
            $this->waitUntilTrue(function () use ($subnet_data) {
                $this->output->write('.');

                return $this->subnetExists($subnet_data['id']);
            });
            $this->newLine();
            $this->replaceConfigKey('subnets.'.$subnet_key.'.id', $subnet_data['id'], true, $this->vpcConfigFile);
        }

        $this->vpc_nat_gateways = config('aws-vpc.nat_gateways');
        $natgw_id = $this->vpc_nat_gateways[$subnet_data['natgw_key']]['id'];
        if (empty($natgw_id)) {
            $this->error('NAT Gateway not found, please check your VPC configuration ');
            exit(1);
        }

        if (! empty($subnet_data['route-table-id'])) {
            if (! $this->routeTableExists($subnet_data['route-table-id'])) {
                $this->warn('Route Table found, but not in AWS, creating new route table');
                $subnet_data['route-table-id'] = null;
            } else {
                if (! $this->isRoutePresentInRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $natgw_id)) {
                    $this->addRouteToRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $natgw_id);
                    $this->info('Waiting for NAT Gateway Route to be available...');
                    $this->waitUntilTrue(function () use ($subnet_data, $natgw_id) {
                        $this->output->write('.');

                        return $this->isRoutePresentInRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $natgw_id);
                    });
                    $this->newLine();
                }
            }
        }

        if (empty($subnet_data['route-table-id'])) {
            $subnet_data['route-table-id'] = $this->createRouteTable($this->vpc_id, 'route-table-'.$subnet_key);
            $this->info('Waiting for Route Table to be available...');
            $this->waitUntilTrue(function () use ($subnet_data) {
                $this->output->write('.');

                return $this->routeTableExists($subnet_data['route-table-id']);
            });
            $this->newLine();
            $this->addRouteToRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $natgw_id);
            $this->info('Waiting for 0.0.0.0/0 route to be added to '.$natgw_id.'...');
            $this->waitUntilTrue(function () use ($subnet_data, $natgw_id) {
                $this->output->write('.');

                return $this->isRoutePresentInRouteTable($subnet_data['route-table-id'], '0.0.0.0/0', $natgw_id);
            });
            $this->newLine();
            $this->setRouteTableIdToSubnet($subnet_data['id'], $subnet_data['route-table-id']);
            $this->info('Waiting for Subnet to be attached to Route Table...');
            $this->waitUntilTrue(function () use ($subnet_data) {
                $this->output->write('.');

                return $this->getSubnetAssociationKey($subnet_data['id'], 'RouteTableId') === $subnet_data['route-table-id'];
            });
            $this->newLine();
            $this->replaceConfigKey('subnets.'.$subnet_key.'.route-table-id', $subnet_data['route-table-id'], true, $this->vpcConfigFile);
        }

        $this->info('VPC (NAT) Subnet '.$subnet_data['id'].': '."\t\t\t".'[<comment>OK</comment>]');
    }

    private function processSubnetsAndRouteTables(): void
    {
        $this->routeTableMainName = $this->app_prefix.'-main';
        $this->routeTablePublicIGWName = $this->app_prefix.'-public-igw';
        $this->routeTablePublicNatName = $this->app_prefix.'-public-nat';

        foreach ($this->vpc_subnets as $subnet_key => $subnet_data) {
            if ($subnet_data['access_type'] == 'public-igw') {
                $this->processSubnetPublic($subnet_key, $subnet_data);
            }
        }

        foreach ($this->vpc_nat_gateways as $nat_gateway_key => $nat_gateway_data) {
            $this->processNATGW($nat_gateway_key, $nat_gateway_data);
        }

        foreach ($this->vpc_subnets as $subnet_key => $subnet_data) {
            if ($subnet_data['access_type'] == 'public-nat') {
                $this->processSubnetPublicNat($subnet_key, $subnet_data);
            }
        }

        $availability_zones = $this->getAvailabilityZones();

        foreach ($this->vpc_subnets as $subnet_key => $subnet_data) {
            if ($subnet_data['access_type'] == 'main') {

                if (! empty($subnet_data['id']) && ! $this->subnetExists($subnet_data['id'])) {
                    $this->warn('Subnet found, but not in AWS, creating new subnet [<comment>'.$subnet_data['name'].']</comment>');
                    $subnet_data['id'] = null;
                }

                if (empty($subnet_data['id'])) {
                    $subnet_data['id'] = $this->createSubnet(
                        $this->vpc_id,
                        $subnet_data['cidr'],
                        $subnet_data['name'],
                        $availability_zones[$subnet_data['availability_zone']]
                    )['Subnet']['SubnetId'];
                    $this->info('Waiting for Subnet to be available...');
                    $this->waitUntilTrue(function () use ($subnet_data) {
                        $this->output->write('.');

                        return $this->subnetExists($subnet_data['id']);
                    });
                    $this->newLine();
                    $this->replaceConfigKey('subnets.'.$subnet_key.'.id', $subnet_data['id'], true, $this->vpcConfigFile);
                }
                $this->info('VPC (PRIVATE) Subnet '.$subnet_data['id'].': '."\t\t\t".'[<comment>OK</comment>]');
            }
        }

        $routeTablesForVpc = $this->getRouteTablesForVpc($this->vpc_id);

        $routeTablesInVpc = [];
        $mainTable = '';
        foreach ($routeTablesForVpc['RouteTables'] as $routeTable) {
            $routeTableId = $routeTable['RouteTableId'];
            if (isset($routeTable['Associations'][0]['Main']) && $routeTable['Associations'][0]['Main'] !== false) {
                $routeTablesInVpc[$this->routeTableMainName] = $routeTableId;
                $mainTable = $routeTableId;
            } else {
                if (isset($routeTable['Tags'])) {
                    foreach ($routeTable['Tags'] as $route) {
                        if ($route['Key'] == 'Name') {
                            $routeTablesInVpc[$route['Value']] = $routeTableId;
                        }
                    }
                }
            }
        }
        $this->setRouteTableName($mainTable, $this->app_prefix.'-route-table-main');
    }

    private function processIGW(): void
    {
        if (! empty($this->vpc_igw_id)) {
            if (! $this->internetGatewayExists($this->vpc_igw_id)) {
                $this->line('Internet Gateway ID: <comment>'.$this->vpc_igw_id.'</comment> not found in AWS, creating...');
                $this->vpc_igw_id = null;
            }
        }

        if (empty($this->vpc_igw_id)) {
            $igw_data = $this->createInternetGateway(config('aws-infra.vpc.internet_gateway.name'));
            $this->vpc_igw_id = $igw_data['InternetGateway']['InternetGatewayId'];
            $this->replaceConfigKey('vpc.internet_gateway.id', $this->vpc_igw_id, true, $this->vpcConfigFile);
            $this->waitUntilTrue(function () {
                return $this->internetGatewayExists($this->vpc_igw_id);
            });
        }

        if ($this->internetGatewayExists($this->vpc_igw_id)) {
            $this->info('Internet Gateway ID: '.$this->vpc_igw_id."\t\t".'[<comment>OK</comment>]');
        }

        $igw_attached = $this->isInternetGatewayAttachedToVpc($this->vpc_id, $this->vpc_igw_id);

        if ($igw_attached === false) {
            $this->warn('Internet Gateway not attached to VPC, attaching...');
            $this->attachInternetGatewayToVpc($this->vpc_igw_id, $this->vpc_id);
            $this->waitUntilTrue(function () {
                return $this->isInternetGatewayAttachedToVpc($this->vpc_id, $this->vpc_igw_id);
            });
        }

        $this->info('Internet Gateway Attached to VPC: '.$this->vpc_id."\t".'[<comment>OK</comment>]');
    }

    private function processVpc(): void
    {

        if (! empty($this->vpc_id)) {
            if (! $this->vpcExists($this->vpc_id)) {
                $this->line('VPC ID: <comment>'.$this->vpc_id.'</comment> not found in AWS, creating...');
                $this->vpc_id = null;
            }
        }

        // Create VPC
        if (empty($this->vpc_id)) {
            $vpcData = $this->createVpc($this->vpc_name, $this->vpc_cidr);
            $this->vpc_id = $vpcData['Vpc']['VpcId'];
            $this->warn('Waiting for VPC to be available...');
            $this->waitUntilTrue(function () {
                return $this->vpcExists($this->vpc_id);
            });
            $this->replaceConfigKey('vpc.id', $this->vpc_id, true, $this->vpcConfigFile);
            $this->line('VPC ID: <comment>'.$this->vpc_id.'</comment> created');
        }

        if ($this->vpcExists($this->vpc_id)) {
            $this->info('VPC ID: '.$this->vpc_id."\t\t\t\t".'[<comment>OK</comment>]');
        }
    }

    /****** DOWN WIP ********/
    private function processDomains(): void
    {
        $this->externalDomainName = config('aws-infra.route53.external_hosted_zone.name');
        $this->internalDomainName = config('aws-infra.route53.internal_hosted_zone.name');
        $this->externalDomainId = config('aws-infra.route53.external_hosted_zone.id');
        $this->internalDomainId = config('aws-infra.route53.internal_hosted_zone.id');

        $domains = $this->route53GetDomains();
        foreach ($domains as $domain) {
            if ($domain['Name'] === $this->externalDomainName.'.') {
                if ($this->externalDomainId !== $domain['Id']) {
                    $this->replaceConfigKey('route53.external_hosted_zone.id', $domain['Id'], true, $this->vpcConfigFile);
                    $this->warn('External Domain ID (Changed): ['.$domain['Id'].'] '.$this->externalDomainName.' found');
                } else {
                    $this->info('External Domain ID: ['.$domain['Id'].'] '.$this->externalDomainName.' found');
                }

            }
            if ($domain['Name'] === $this->internalDomainName.'.') {
                if ($this->internalDomainId !== $domain['Id']) {
                    $this->replaceConfigKey('route53.internal_hosted_zone.id', $domain['Id'], true, $this->vpcConfigFile);
                    $this->info('Internal Domain ID (Changed): '.$domain['Id'].' '.$this->internalDomainName.' found');
                } else {
                    $this->info('Internal Domain ID: '.$domain['Id'].' '.$this->internalDomainName.' found');
                }
            }
        }
        if (empty($this->externalDomainId) && ! empty($this->externalDomainName)) {
            $this->externalDomainId = $this->route53CreateDomain($this->externalDomainName);
            $this->replaceConfigKey('route53.external_hosted_zone.id', $this->externalDomainId, true, $this->vpcConfigFile);
            $this->warn('External Domain ID (Created): ['.$this->externalDomainId.'] '.$this->externalDomainName.' found');
        }
        if (empty($this->internalDomainId) && ! empty($this->internalDomainName)) {
            $this->internalDomainId = $this->route53CreateDomain($this->internalDomainName, true, $this->vpc_id);
            $this->replaceConfigKey('route53.internal_hosted_zone.id', $this->internalDomainId, true, $this->vpcConfigFile);
            $this->info('Internal Domain ID (Created): ['.$this->internalDomainId.'] '.$this->internalDomainName.' found');
        }
    }

    private function processECSCluster(): void
    {
        $this->ecsClusterArn = config('aws-infra.ecs.cluster.arn');
        $this->ecsClusterName = config('aws-infra.ecs.cluster.name');
        if (! empty($this->ecsClusterArn)) {
            if (! $this->ecsClusterExists($this->ecsClusterArn)) {
                $this->warn('ECS Cluster not found, please check your VPC configuration');
                $this->ecsClusterArn = null;
            }
        }

        if (empty($this->ecsClusterArn)) {
            $this->ecsClusterArn = $this->ecsCreateCluster($this->ecsClusterName);
            $this->replaceConfigKey('ecs.cluster.arn', $this->ecsClusterArn, true, $this->vpcConfigFile);
            $this->waitUntilTrue(function () {
                $this->warn('Waiting for ECS Cluster to be available...');

                return $this->ecsClusterExists($this->ecsClusterArn);
            });
        }

        if ($this->ecsClusterExists($this->ecsClusterArn)) {
            $this->info('ECS Cluster ID: '.$this->ecsClusterArn.' created');
        }
    }
}
