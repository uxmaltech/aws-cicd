<?php

namespace Uxmal\Devtools\Command\Aws;

use Random\RandomException;

class CreateInfrastructureCommand extends AWSCommand
{
    protected $signature = 'aws:create-infrastructure 
                                {--aws-access-key=}
                                {--aws-access-secret=}
                                {--aws-region=}
                                {--aws-token=}';

    protected $description = 'Crea los dominios en Route53';

    private string $awsInfraConfigFile;

    private string $appPrefix;

    private ?string $vpcId;

    private ?string $igwId;

    private array $subnets;

    private string $mainRouteTable;

    private array $routeTables;

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
        $this->info('=========== Environment Data ===========');

        $headers = ['Variable', 'Valor'];
        $envTable = [
            ['Name', config('aws-infra.app.name')],
            ['Prefix', config('aws-infra.app.prefix')],
            ['Key', config('aws-infra.app.key')],
            ['Host', config('aws-infra.app.subdomain') . '.' . config('aws-infra.app.domain')],
            ['VPC Network', config('aws-infra.app.vpc_network')],
            ['VPC Netmask', config('aws-infra.app.vpc_netmask')],
            ['VPC Subnet Netmask', config('aws-infra.app.vpc_subnet_netmask')],
            ['VPC Name', config('aws-infra.vpc.name')],
            ['VPC CIDR', config('aws-infra.vpc.cidr')],
            ['VPC Internet Gateway Name', config('aws-infra.vpc.internet_gateway.name')],
            ['VPC Subnet Private AWZ AZ1', config('aws-infra.vpc.subnets.private-awz-az1.access_type') . ', ' . config('aws-infra.vpc.subnets.private-awz-az1.cidr') . ' [' . config('aws-infra.vpc.subnets.private-awz-az1.id') . ']'],
            ['VPC Subnet Private AWZ AZ2', config('aws-infra.vpc.subnets.private-awz-az2.access_type') . ', ' . config('aws-infra.vpc.subnets.private-awz-az2.cidr') . ' [' . config('aws-infra.vpc.subnets.private-awz-az2.id') . ']'],
            ['VPC Subnet Public AWZ AZ1', config('aws-infra.vpc.subnets.public-aws-az1.access_type') . ', ' . config('aws-infra.vpc.subnets.public-aws-az1.cidr') . ' [' . config('aws-infra.vpc.subnets.public-aws-az1.id') . ']'],
            ['VPC Subnet Public AWZ AZ2', config('aws-infra.vpc.subnets.public-aws-az2.access_type') . ', ' . config('aws-infra.vpc.subnets.public-aws-az2.cidr') . ' [' . config('aws-infra.vpc.subnets.public-aws-az2.id') . ']'],
            ['VPC Subnet NAT AWZ AZ1', config('aws-infra.vpc.subnets.nat-aws-az1.access_type') . ', ' . config('aws-infra.vpc.subnets.nat-aws-az1.cidr') . ' [' . config('aws-infra.vpc.subnets.nat-aws-az1.id') . ']'],
            ['VPC Subnet NAT AWZ AZ2', config('aws-infra.vpc.subnets.nat-aws-az2.access_type') . ', ' . config('aws-infra.vpc.subnets.nat-aws-az2.cidr') . ' [' . config('aws-infra.vpc.subnets.nat-aws-az2.id') . ']'],
            ['Route53 external domain', config('aws-infra.route53.external_hosted_zone.name')],
            ['Route53 internal domain', config('aws-infra.route53.internal_hosted_zone.name')],
            ['ECS Cluster Name', config('aws-infra.ecs.cluster.name')],
        ];
        $this->table($headers, $envTable);

        if (!$this->confirm('¿Es correcto, proceder a la creación en AWS?')) {
            $this->error('Abortando...');
            exit(1);
        }

        $appName = config('aws-infra.app.name');
        $this->appPrefix = config('aws-infra.app.prefix');
        $this->awsInfraConfigFile = config_path('aws-infra.php');

        if ($this->isDryRun()) {
            $this->info('Dry run, no se creará nada en AWS');
            exit(0);
        }
        $this->newLine();

        // Create/Check VPC
        $this->processVpc();

        // Create/Check Internet Gateway
        $this->processIGW();

        $this->routeTableMainName = $this->appPrefix . '-main';
        $this->routeTablePublicIGWName = $this->appPrefix . '-public-igw';
        $this->routeTablePublicNatName = $this->appPrefix . '-public-nat';

        $this->subnets = config('aws-infra.vpc.subnets');

        // Create/Check RouteTables
        $this->processRouteTables();

        exit(0);

        if (!array_key_exists($this->routeTableMainName, $this->routeTables) || !array_key_exists($this->routeTablePublicIGWName, $this->routeTables)) {
            $this->error('No private or public route table found, please check your VPC configuration');
            exit(1);
        }

        if (!$this->isInternetGatewayRouteOnRouteTable($this->routeTables[$this->routeTablePublicIGWName], $this->igwId)) {
            $this->error('No IGW route found on public route table, please check your VPC configuration');
            exit(1);
        } else {
            $this->info('IGW [' . $this->igwId . ']route attached on public route table [' . $this->routeTables[$this->routeTablePublicIGWName] . ']');
        }

        // Create/Check Subnets
        $this->processSubnets();

        // Create/Check Domains
        $this->processDomains();

        // Create/Check ECS Cluster
        $this->processECSCluster();

        // Create/Check ECS Task Definitions

    }

    private function processDomains(): void
    {
        $this->externalDomainName = config('aws-infra.route53.external_hosted_zone.name');
        $this->internalDomainName = config('aws-infra.route53.internal_hosted_zone.name');
        $this->externalDomainId = config('aws-infra.route53.external_hosted_zone.id');
        $this->internalDomainId = config('aws-infra.route53.internal_hosted_zone.id');

        $domains = $this->route53GetDomains();
        foreach ($domains as $domain) {
            if ($domain['Name'] === $this->externalDomainName . '.') {
                if ($this->externalDomainId !== $domain['Id']) {
                    $this->replaceConfigKey('route53.external_hosted_zone.id', $domain['Id'], true, $this->awsInfraConfigFile);
                    $this->warn('External Domain ID (Changed): [' . $domain['Id'] . '] ' . $this->externalDomainName . ' found');
                } else {
                    $this->info('External Domain ID: [' . $domain['Id'] . '] ' . $this->externalDomainName . ' found');
                }

            }
            if ($domain['Name'] === $this->internalDomainName . '.') {
                if ($this->internalDomainId !== $domain['Id']) {
                    $this->replaceConfigKey('route53.internal_hosted_zone.id', $domain['Id'], true, $this->awsInfraConfigFile);
                    $this->info('Internal Domain ID (Changed): ' . $domain['Id'] . ' ' . $this->internalDomainName . ' found');
                } else {
                    $this->info('Internal Domain ID: ' . $domain['Id'] . ' ' . $this->internalDomainName . ' found');
                }
            }
        }
        if (empty($this->externalDomainId) && !empty($this->externalDomainName)) {
            $this->externalDomainId = $this->route53CreateDomain($this->externalDomainName);
            $this->replaceConfigKey('route53.external_hosted_zone.id', $this->externalDomainId, true, $this->awsInfraConfigFile);
            $this->warn('External Domain ID (Created): [' . $this->externalDomainId . '] ' . $this->externalDomainName . ' found');
        }
        if (empty($this->internalDomainId) && !empty($this->internalDomainName)) {
            $this->internalDomainId = $this->route53CreateDomain($this->internalDomainName, true, $this->vpcId);
            $this->replaceConfigKey('route53.internal_hosted_zone.id', $this->internalDomainId, true, $this->awsInfraConfigFile);
            $this->info('Internal Domain ID (Created): [' . $this->internalDomainId . '] ' . $this->internalDomainName . ' found');
        }
    }

    private function processECSCluster(): void
    {
        $this->ecsClusterArn = config('aws-infra.ecs.cluster.arn');
        $this->ecsClusterName = config('aws-infra.ecs.cluster.name');
        if (!empty($this->ecsClusterArn)) {
            if (!$this->ecsClusterExists($this->ecsClusterArn)) {
                $this->warn('ECS Cluster not found, please check your VPC configuration');
                $this->ecsClusterArn = null;
            }
        }

        if (empty($this->ecsClusterArn)) {
            $this->ecsClusterArn = $this->ecsCreateCluster($this->ecsClusterName);
            $this->replaceConfigKey('ecs.cluster.arn', $this->ecsClusterArn, true, $this->awsInfraConfigFile);
            $this->waitUntilTrue(function () {
                $this->warn('Waiting for ECS Cluster to be available...');

                return $this->ecsClusterExists($this->ecsClusterArn);
            });
        }

        if ($this->ecsClusterExists($this->ecsClusterArn)) {
            $this->info('ECS Cluster ID: ' . $this->ecsClusterArn . ' created');
        }
    }

    private function processSubnets(): void
    {
        /**
         * Build Subnets
         */
        foreach ($this->subnets as $subnet_name => &$subnet_data) {
            if (!empty($subnet_data['id'])) {
                if (!$this->subnetExists($subnet_data['id'])) {
                    $this->warn('Subnet found, but not in AWS, creating [' . $subnet_name . '] => ' . $subnet_data['id']);
                    $subnet_data['id'] = null;
                }
            } else {
                $this->info('Subnet not found, creating [' . $subnet_name . ']');
                $result = $this->createSubnet($this->vpcId, $subnet_data['cidr'], $subnet_name);
                $subnet_data['id'] = $result['Subnet']['SubnetId'];
                $this->replaceConfigKey('vpc.subnets.' . $subnet_name . '.id', $subnet_data['id'], true, $this->awsInfraConfigFile);
            }

            switch ($subnet_data['access_type']) {
                case 'public-igw':
                    if (empty($subnet_data['id'])) {
                        $subnet_data['id'] = $this->createSubnet($this->vpcId, $subnet_data['cidr'], $subnet_name)['Subnet']['SubnetId'];
                        $this->replaceConfigKey('vpc.subnets.' . $subnet_name . '.id', $subnet_data['id'], true, $this->awsInfraConfigFile);
                    }
                    $currentRouteTableId = $this->getSubnetAssociationKey($subnet_data['id'], 'RouteTableId');
                    if ($currentRouteTableId !== $this->routeTables[$this->routeTablePublicIGWName]) {
                        $this->setRouteTableIdToSubnet($subnet_data['id'], $this->routeTables[$this->routeTablePublicIGWName]);
                    }
                    $currentRouteTableId = $this->getSubnetAssociationKey($subnet_data['id'], 'RouteTableId');
                    if ($currentRouteTableId === $this->routeTables[$this->routeTablePublicIGWName]) {
                        $this->info("Subnet $subnet_name [{$subnet_data['id']}], and has the route table $this->routeTablePublicIGWName attached");
                    }
                    break;
                case 'public-nat':
                    $this->info('Subnet is public-nat, checking it...');
                    if (empty($subnet_data['id'])) {
                        $subnet_data['id'] = $this->createSubnet($this->vpcId, $subnet_data['cidr'], $subnet_name)['Subnet']['SubnetId'];
                        $this->replaceConfigKey('vpc.subnets.' . $subnet_name . '.id', $subnet_data['id'], true, $this->awsInfraConfigFile);
                    }

                    /** Check if public-nat routeTable, exists if not create!*/
                    if (empty($this->routeTables[$this->routeTablePublicNatName])) {
                        $this->routeTables[$this->routeTablePublicNatName] = $this->createRouteTable($this->vpcId, $this->routeTablePublicNatName);
                    }

                    /** Check if public-nat routeTable, has NATGw Attached */
                    $currentRouteTableId = $this->getSubnetAssociationKey($subnet_data['id'], 'RouteTableId');

                    if ($currentRouteTableId !== $this->routeTables[$this->routeTablePublicNatName]) {
                        $this->setRouteTableIdToSubnet($subnet_data['id'], $this->routeTables[$this->routeTablePublicNatName]);
                    }

                    $currentRouteTableId = $this->getSubnetAssociationKey($subnet_data['id'], 'RouteTableId');
                    if ($currentRouteTableId === $this->routeTables[$this->routeTablePublicNatName]) {
                        $this->info("Subnet $subnet_name [{$subnet_data['id']}], and has the route table $this->routeTablePublicNatName attached");
                    }

                    /** Check if public-nat routeTable, has NATGw Attached */
                    if (!$this->isNatGwRouteOnRouteTable($this->routeTables[$this->routeTablePublicNatName], $nat_gateway_id)) {
                        $this->addRouteToRouteTable($this->routeTables[$this->routeTablePublicNatName], '0.0.0.0/0', $nat_gateway_id);
                        $this->waitUntilTrue(function () use ($nat_gateway_id) {
                            $this->warn('Waiting for NAT Gateway Route to be available...');

                            return $this->isNatGwRouteOnRouteTable($this->routeTables[$this->routeTablePublicNatName], $nat_gateway_id);
                        });
                        $this->info("Route to NAT Gateway ($nat_gateway_id) added to Route Table $this->routeTablePublicNatName");
                    }
                    break;
                case 'private':
                    if (empty($subnet_data['id'])) {
                        $this->error('Subnet is private, but no ID found, please check your VPC configuration');
                        $subnet_data['id'] = $this->createSubnet($this->vpcId, $subnet_data['cidr'], $subnet_name)['Subnet']['SubnetId'];
                        $this->replaceConfigKey('vpc.subnets.' . $subnet_name . '.id', $subnet_data['id'], true, $this->awsInfraConfigFile);
                    }
                    break;
            }

        }
    }

    private function subnetCheckIfExistsOrCreate(array $subnet_data)
    {
        if (!array_key_exists('key', $subnet_data) || empty($subnet_data['key'])) {
            $this->error('EL parametro key es obligatorio para la subnet');
            exit(1);
        }
        $availability_zones = $this->getAvailabilityZones();

        if (!empty($subnet_data['id'] && !$this->subnetExists($subnet_data['id']))) {
            $this->warn('Subnet found, but not in AWS, creating [' . $subnet_data['name'] . '] => ' . $subnet_data['id']);
            $subnet_data['id'] = null;
        }

        if (empty($subnet_data['id'])) {
            $this->warn('Subnet not exists, creating [' . $subnet_data['name'] . ']');
            $az = substr($subnet_data['key'], -3, 3);
            if (!isset($availability_zones[$az])) {
                $this->error('AZ not found, please check your VPC configuration [' . $az . ']');
                exit(1);
            }
            $result = $this->createSubnet($this->vpcId, $subnet_data['cidr'], $subnet_data['name'], $availability_zones[$az]);
            $this->replaceConfigKey('vpc.subnets.' . $subnet_data['key'] . '.id', $result['Subnet']['SubnetId'], true, $this->awsInfraConfigFile);
            $subnet_data['id'] = $result['Subnet']['SubnetId'];
        }

        return $subnet_data['id'];
    }

    private function processRouteTables(): void
    {
        $routeTablesForVpc = $this->getRouteTablesForVpc($this->vpcId);

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

        $availability_zones = $this->getAvailabilityZones();
        foreach ($this->subnets as $subnet => $data) {
            if (empty($access_types[$data['access_type']])) {
                $access_types[$data['access_type']] = [];
            }
            $access_types[$data['access_type']][] = $data + ['key' => $subnet];
        }

        $nat_gateways = config('aws-infra.vpc.nat_gateways');

        if ($nat_gateways) {
            foreach ($nat_gateways as $natgw_name => $natgw_data) {
                if( !empty($natgw_data['elastic_ip']) && !$this->elasticIpExists($natgw_data['elastic_ip'])) {
                    $this->warn('Elastic IP not found, creating [elip-' . $natgw_name . ']');
                    $natgw_data['elastic_ip'] = null;
                }

                if( empty($natgw_data['elastic_ip']) ) {
                    $natgw_data['elastic_ip'] = $this->createEipAlloc('elip-' . $natgw_name);
                    $this->waitUntilTrue(function () use ($natgw_data) {
                        $this->warn('Waiting for Elastic IP to be available...');
                        return $this->elasticIpExists($natgw_data['elastic_ip']);
                    });
                    $this->replaceConfigKey('vpc.nat_gateways.' . $natgw_name . '.elastic_ip', $natgw_data['elastic_ip'], true, $this->awsInfraConfigFile);
                }

                $this->info('Elastic IP [' . $natgw_data['elastic_ip'] . ']  --OK--');


                if (!empty($natgw_data['id'])) {
                    if (!$this->internetNatGatewayExists($natgw_data['id'])) {
                        $this->warn('NAT Gateway found, but not in AWS, creating [' . $natgw_data['id'] . ']');
                        $natgw_data['id'] = null;
                    }
                }

                if (empty($natgw_data['id'])) {
                    $this->info('NAT Gateway not found, creating [' . $natgw_name . ']');
                    $subnet_data = $this->subnets[$natgw_data['subnet_key']] + ['key' => $natgw_data['subnet_key']];
                    $subnet_data['id'] = $this->subnetCheckIfExistsOrCreate($subnet_data);

                    if (empty($subnet_data['id'])) {
                        $this->error('Subnet not found, please check your VPC configuration ' . __FILE__ . ':' . __LINE__);
                        exit(1);
                    }

                    $natgw_data['id'] = $this->createNatGateway($subnet_data['id'], $natgw_data['elastic_ip'], $natgw_name);
                    $this->waitUntilTrue(function () use ($natgw_data) {
                        $this->warn('Waiting for NAT Gateway to be available...');

                        return $this->internetNatGatewayExists($natgw_data['id']);
                    });
                    $this->replaceConfigKey('vpc.nat_gateways.' . $natgw_name . '.id', $natgw_data['id'], true, $this->awsInfraConfigFile);
                }

                $this->info('NAT Gateway [' . $natgw_data['id'] . ']  --OK--');
            }
        }


        /********* nat subnets *********
         * /*******************************/
        foreach ($access_types['public-nat'] as $subnet) {
            /**
             * Check if subnetExists if not create.
             */
            if (!$this->subnetExists($subnet['id']) || empty($subnet['id'])) {
                $this->info('Subnet not found, creating [' . $subnet['name'] . ']');
                $az = substr($subnet['key'], -3, 3);
                if (!isset($availability_zones[$az])) {
                    $this->error('AZ not found, please check your VPC configuration [' . $az . ']');
                    exit(1);
                }
                $result = $this->createSubnet($this->vpcId, $subnet['cidr'], $subnet['name'], $availability_zones[$az]);
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.id', $result['Subnet']['SubnetId'], true, $this->awsInfraConfigFile);
                $subnet['id'] = $result['Subnet']['SubnetId'];
            }

            /**
             * Check configuration file for route table id
             */
            $configRouteTableId = $subnet['route-table-id'] ?? null;
            $currentAttachedRouteTableId = $this->getSubnetAssociationKey($subnet['id'], 'RouteTableId');
            $currentVPCRouteTableId = $routeTablesInVpc[$subnet['key']] ?? null;

            if (!empty($configRouteTableId)) {
                $this->warn('Route Table ID found in config file, checking if exists [' . $configRouteTableId . ']');
                if (!$this->routeTableExists($configRouteTableId)) {
                    $this->warn('Route Table not found, reseting [' . $configRouteTableId . ']');
                    $configRouteTableId = null;
                }
            }

            /** Check Route Table attached to subnet */
            if (!empty($currentAttachedRouteTableId) && empty($configRouteTableId)) {
                $this->warn('Route Table ID found attached to subnet [' . $currentAttachedRouteTableId . '], but not found in config file');
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.route-table-id', $currentAttachedRouteTableId, true, $this->awsInfraConfigFile);
                $configRouteTableId = $currentAttachedRouteTableId;
            }

            /** Check Route Table with VPC Routes Classified by name */
            if (!empty($currentVPCRouteTableId)) {
                if ($currentVPCRouteTableId != $configRouteTableId) {
                    $this->warn('Route Table ID found in config file [' . $configRouteTableId . '], but differs for VPC routeTablesExists [' . $currentVPCRouteTableId . ']');
                }
                if ($currentVPCRouteTableId != $currentAttachedRouteTableId) {
                    $this->warn('Route Table ID found in config file [' . $configRouteTableId . '], but differs for attached to subnet [' . $currentAttachedRouteTableId . ']');
                }
            }

            /**
             * Create a route table
             */
            if (empty($configRouteTableId)) {
                $this->warn('Route Table not found, creating [' . $subnet['key'] . ']');
                $configRouteTableId = $this->createRouteTable($this->vpcId, $subnet['key']);
                $this->waitUntilTrue(function () use ($configRouteTableId) {
                    $this->warn('Waiting for Route Table to be available...');

                    return $this->routeTableExists($configRouteTableId);
                });
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.route-table-id', $configRouteTableId, true, $this->awsInfraConfigFile);
                $this->setRouteTableIdToSubnet($subnet['id'], $configRouteTableId);
            } else {
                /**
                 * Check if routeTable has attached to subnet
                 */
                $currentRouteTableId = $this->getSubnetAssociationKey($subnet['id'], 'RouteTableId');
                if ($currentRouteTableId !== $configRouteTableId) {
                    $this->setRouteTableIdToSubnet($subnet['id'], $configRouteTableId);
                }

                if ($this->getSubnetAssociationKey($subnet['id'], 'RouteTableId') !== $routeTablesInVpc[$subnet['key']]) {
                    $this->error('Route Table ID found in config file [' . $configRouteTableId . '], but differs for VPC routeTablesExists [' . $routeTablesInVpc[$subnet['key']] . ']');
                    exit(1);
                }
            }
            $this->info("Subnet {$subnet['key']} [{$subnet['id']}], and has the route table $configRouteTableId attached");

            /**
             * Check if route table has NATGw Route Attached
             */
            $nat_gateway_id = config('aws-infra.vpc.nat_gateways.' . $subnet['natgw_key'] . '.id');
            if( empty($nat_gateway_id) ) {
                $this->error('NAT Gateway ID not found, please check your VPC configuration ' . __FILE__ . ':' . __LINE__);
                exit(1);
            }

            $this->warn('Nat Gateway Id: ' . $nat_gateway_id . ' created...');

            if (!$this->isNatGwRouteOnRouteTable($configRouteTableId, $nat_gateway_id)) {
                $this->addRouteToRouteTable($configRouteTableId, '0.0.0.0/0', $nat_gateway_id);
                $this->waitUntilTrue(function () use ($configRouteTableId, $nat_gateway_id) {
                    $this->warn('Waiting for route 0.0.0.0/0 Route to be added to ' . $configRouteTableId . ' routeTable...');

                    return $this->isNatGwRouteOnRouteTable($configRouteTableId, $nat_gateway_id);
                });
            }

            if ($this->subnetExists($subnet['id'])) {
                $this->info('Subnet [' . $subnet['name'] . '] created ...');
            }

        }

        /******* public subnets *******
         * /******************************/

        foreach ($access_types['public-igw'] as $subnet) {
            /**
             * Check if subnetExists if not create.
             */
            if (!$this->subnetExists($subnet['id']) || empty($subnet['id'])) {
                $this->info('Subnet not found, creating [' . $subnet['name'] . ']');
                $az = substr($subnet['key'], -3, 3);
                if (!isset($availability_zones[$az])) {
                    $this->error('AZ not found, please check your VPC configuration [' . $az . ']');
                    exit(1);
                }
                $result = $this->createSubnet($this->vpcId, $subnet['cidr'], $subnet['name'], $availability_zones[$az]);
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.id', $result['Subnet']['SubnetId'], true, $this->awsInfraConfigFile);
                $subnet['id'] = $result['Subnet']['SubnetId'];
            }

            /**
             * Check configuration file for route table id
             */
            $configRouteTableId = $subnet['route-table-id'] ?? null;
            $currentAttachedRouteTableId = $this->getSubnetAssociationKey($subnet['id'], 'RouteTableId');
            $currentVPCRouteTableId = $routeTablesInVpc[$subnet['key']] ?? null;

            if (!empty($configRouteTableId)) {
                $this->warn('Route Table ID found in config file, checking if exists [' . $configRouteTableId . ']');
                if (!$this->routeTableExists($configRouteTableId)) {
                    $this->warn('Route Table not found, reseting [' . $configRouteTableId . ']');
                    $configRouteTableId = null;
                }
            }

            /** Check Route Table attached to subnet */
            if (!empty($currentAttachedRouteTableId) && empty($configRouteTableId)) {
                $this->warn('Route Table ID found attached to subnet [' . $currentAttachedRouteTableId . '], but not found in config file');
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.route-table-id', $currentAttachedRouteTableId, true, $this->awsInfraConfigFile);
                $configRouteTableId = $currentAttachedRouteTableId;
            }

            /** Check Route Table with VPC Routes Classified by name */
            if (!empty($currentVPCRouteTableId)) {
                if ($currentVPCRouteTableId != $configRouteTableId) {
                    $this->warn('Route Table ID found in config file [' . $configRouteTableId . '], but differs for VPC routeTablesExists [' . $currentVPCRouteTableId . ']');
                }
                if ($currentVPCRouteTableId != $currentAttachedRouteTableId) {
                    $this->warn('Route Table ID found in config file [' . $configRouteTableId . '], but differs for attached to subnet [' . $currentAttachedRouteTableId . ']');
                }
            }

            /**
             * Create a route table
             */
            if (empty($configRouteTableId)) {
                $this->warn('Route Table not found, creating [' . $subnet['key'] . ']');
                $configRouteTableId = $this->createRouteTable($this->vpcId, $subnet['key']);
                $this->waitUntilTrue(function () use ($configRouteTableId) {
                    $this->warn('Waiting for Route Table to be available...');

                    return $this->routeTableExists($configRouteTableId);
                });
                $this->setRouteTableIdToSubnet($subnet['id'], $configRouteTableId);
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.route-table-id', $configRouteTableId, true, $this->awsInfraConfigFile);
            } else {
                /**
                 * Check if routeTable has attached to subnet
                 */
                $currentRouteTableId = $this->getSubnetAssociationKey($subnet['id'], 'RouteTableId');
                if ($currentRouteTableId !== $configRouteTableId) {
                    $this->setRouteTableIdToSubnet($subnet['id'], $configRouteTableId);
                }

                if ($this->getSubnetAssociationKey($subnet['id'], 'RouteTableId') !== $routeTablesInVpc[$subnet['key']]) {
                    $this->error('Subnet ' . $subnet['key'] . ' not attached to route table ' . $routeTablesInVpc[$subnet['key']]);
                    exit(1);
                }
            }
            $this->info("Subnet {$subnet['key']} [{$subnet['id']}], and has the route table $configRouteTableId attached");

            /*
             * Check if route table has IGW Route 0.0.0.0/0 created
             */
            if (!$this->isInternetGatewayRouteOnRouteTable($configRouteTableId, $this->igwId)) {
                $this->addRouteToRouteTable($configRouteTableId, '0.0.0.0/0', $this->igwId);
                $this->waitUntilTrue(function () use ($configRouteTableId) {
                    return $this->isInternetGatewayRouteOnRouteTable($configRouteTableId, $this->igwId);
                });
            }
            $this->info("Default route 0.0.0.0/0 => IGW ($this->igwId) exists in Subnet [{$subnet['id']}]");

        }

        /*************************************************************************
         * PRIVATE SUBNETS
         **************************************************************************/
        foreach ($access_types['main'] as $subnet) {
            /**
             * Check if subnetExists if not create.
             */
            if (!$this->subnetExists($subnet['id']) || empty($subnet['id'])) {
                $this->info('Subnet not found, creating [' . $subnet['name'] . ']');
                $az = substr($subnet['key'], -3, 3);
                if (!isset($availability_zones[$az])) {
                    $this->error('AZ not found, please check your VPC configuration [' . $az . ']');
                    exit(1);
                }
                $result = $this->createSubnet($this->vpcId, $subnet['cidr'], $subnet['name'], $availability_zones[$az]);
                $this->replaceConfigKey('vpc.subnets.' . $subnet['key'] . '.id', $result['Subnet']['SubnetId'], true, $this->awsInfraConfigFile);
                $subnet['id'] = $result['Subnet']['SubnetId'];
            }
        }

        $routeTablesForVpc = [];
        foreach ($this->getRouteTablesForVpc($this->vpcId)['RouteTables'] as $_routeTables) {
            $routeTablesForVpc[$_routeTables['RouteTableId']] = [];
            dump($_routeTables);
            foreach ($_routeTables['Routes'] as $route) {
                if (array_key_exists('DestinationPrefixListId', $route)) {
                    $destCidr = $route['DestinationPrefixListId'] ?? 'unknown';
                } elseif (array_key_exists('DestinationCidrBlock', $route)) {
                    $destCidr = $route['DestinationCidrBlock'] ?? 'unknown';
                }

                if (array_key_exists('GatewayId', $route)) {
                    $gatewayID = $route['GatewayId'];
                } elseif (array_key_exists('NatGatewayId', $route)) {
                    $gatewayID = $route['NatGatewayId'];
                } else {
                    $gatewayID = 'unknown';
                }

                $routeTablesForVpc[$_routeTables['RouteTableId']][] = [
                    'cidr' => $destCidr,
                    'gateway' => $gatewayID,
                ];
            }
        }

        $this->setRouteTableName($mainTable, $this->routeTableMainName);
        /*
                $vpce_id = config('aws-infra.vpc.vpc-endpoint-s3.id');
                $vpce_name = config('aws-infra.vpc.vpc-endpoint-s3.name');

                if (!$this->vpceIdExists($vpce_id)) {
                    $this->warn('VPC Endpoint not found, creating...');
                    $vpce_id = $this->createVPCE($vpce_name, 'com.amazonaws.' . $this->awsCredentialsRegion . '.s3', $this->vpcId, array_keys($routeTablesForVpc));
                    $this->waitUntilTrue(function () use ($vpce_id) {
                        $this->warn('Waiting for VPC Endpoint to be available...');
                        return $this->vpceIdExists($vpce_id);
                    });
                    $this->replaceConfigKey('vpc.vpc-endpoint-s3.id', $vpce_id, true, $this->awsInfraConfigFile);
                }
                $this->warn('VPC Endpoint Id: ' . $vpce_id . ' OK');
                // $this->mainRouteTable = $mainTable;
                // $this->routeTables = $routeTables;
        */
    }

    private function processIGW(): void
    {

        $this->igwId = config('aws-infra.vpc.internet_gateway.id');

        if (!empty($this->igwId)) {
            if (!$this->internetGatewayExists($this->igwId)) {
                $this->warn('Internet Gateway not found [' . $this->igwId . '] in AWS, creating...');
                $this->igwId = null;
            }
        }

        if (empty($this->igwId)) {
            $igw_data = $this->createInternetGateway(config('aws-infra.vpc.internet_gateway.name'));
            $this->igwId = $igw_data['InternetGateway']['InternetGatewayId'];
            $this->replaceConfigKey('vpc.internet_gateway.id', $this->igwId, true, $this->awsInfraConfigFile);
            $this->waitUntilTrue(function () {
                return $this->internetGatewayExists($this->igwId);
            });
        }

        if ($this->internetGatewayExists($this->igwId)) {
            $this->info('Internet Gateway ID: ' . $this->igwId . ' created');
        }

        $igw_attached = $this->isInternetGatewayAttachedToVpc($this->vpcId, $this->igwId);
        if ($igw_attached === false) {
            $this->warn('Internet Gateway not attached to VPC, attaching...');
            $this->attachInternetGatewayToVpc($this->igwId, $this->vpcId);
            $this->waitUntilTrue(function () {
                return $this->isInternetGatewayAttachedToVpc($this->vpcId, $this->igwId);
            });
        }

        $this->info("Internet Gateway ($this->igwId) attached to VPC ($this->vpcId)!");
    }

    private function processVpc(): void
    {
        $this->vpcId = config('aws-infra.vpc.id');

        if (!empty($this->vpcId)) {
            if (!$this->vpcExists($this->vpcId)) {
                $this->warn('VPC ID: ' . $this->vpcId . ' not found in AWS, creating...');
                $this->vpcId = null;
            }
        }

        // Create VPC
        if (empty($this->vpcId)) {
            $vpcData = $this->createVpc(config('aws-infra.vpc.name'), config('aws-infra.vpc.cidr'));
            $this->vpcId = $vpcData['Vpc']['VpcId'];
            $this->replaceConfigKey('vpc.id', $this->vpcId, true, $this->awsInfraConfigFile);
            $this->waitUntilTrue(function () {
                $this->warn('Waiting for VPC to be available...');

                return $this->vpcExists($this->vpcId);
            });
        }

        if ($this->vpcExists($this->vpcId)) {
            $this->info('VPC ID: ' . $this->vpcId . ' created');
        }
    }
}
