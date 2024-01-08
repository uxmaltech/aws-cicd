<?php

namespace Uxmal\Devtools\Traits\AWS;

use Aws\Ec2\Ec2Client;
use Aws\Exception\AwsException;
use Aws\Result;
use Exception;

trait EC2Utils
{
    protected Ec2Client $Ec2Client;

    private array $availabilityZones;

    protected function initEC2Client(): void
    {
        if (empty($this->awsCredentialsRegion) || empty($this->awsCredentialsAccessKey) || empty($this->awsCredentialsSecretKey)) {
            $this->error('No se han definido las credenciales de AWS en el archivo .env');
            exit(1);
        }

        $sessionToken = [];
        if (! empty($this->awsCredentialsToken)) {
            $sessionToken = ['token' => $this->awsCredentialsToken];
        }
        if (! isset($this->Ec2Client)) {
            $this->Ec2Client = new Ec2Client([
                'version' => 'latest',
                'region' => $this->awsCredentialsRegion, // Usar la región desde .env
                'credentials' => [
                    'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                    'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
                ] + $sessionToken,
            ]);

            $this->getAvailabilityZones();
        }

    }

    /***** VPC *****/

    protected function createVpc(string $name, string $cidr): bool|Result
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            return $this->Ec2Client->createVpc([
                'CidrBlock' => $cidr, // Specify your CIDR block
                'DryRun' => $isDryRun, // Add DryRun parameter
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'vpc',
                        'Tags' => [
                            [
                                'Key' => 'Name',
                                'Value' => $name, // Specify your VPC name
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createVpc] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function vpcExists(string $vpcId): bool|array
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeVpcs([
                'DryRun' => $isDryRun, // Add DryRun parameter
            ]);

            foreach ($result['Vpcs'] as $vpc) {
                if ($vpc['VpcId'] === $vpcId) {
                    return true;
                }
            }
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[vpcExists] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    /***** SUBNETS *****/

    public function createSubnet($vpcId, $cidrBlock, $subnetName, string $availabilityZone = 'az1'): Result|false
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            return $this->Ec2Client->createSubnet([
                'VpcId' => $vpcId, // The ID of the VPC
                'CidrBlock' => $cidrBlock, // The CIDR block for the subnet
                'AvailabilityZone' => $availabilityZone, // The Availability Zone for the subnet
                'DryRun' => $isDryRun, // Dry run option
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'subnet',
                        'Tags' => [
                            [
                                'Key' => 'Name',
                                'Value' => $subnetName,
                            ],
                        ],
                    ],
                ],
            ]);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createSubnet] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function subnetExists($subnetId): bool
    {
        if (empty($subnetId)) {
            return false;
        }

        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeSubnets([
                'SubnetIds' => [$subnetId],
                'DryRun' => $isDryRun,
            ]);

            return ! empty($result['Subnets']);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[subnetExists] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    public function getSubnetAssociationKey($subnetId, string $key): ?string
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            $result = $this->Ec2Client->describeRouteTables();
            foreach ($result['RouteTables'] as $routeTables) {
                foreach ($routeTables as $name => $routeTable) {
                    switch ($name) {
                        case 'Associations':
                            foreach ($routeTable as $association) {
                                if (! empty($association['SubnetId']) && $association['SubnetId'] === $subnetId) {
                                    return $association[$key] ?? null;
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[getSubnetAssociationKey] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return null;
    }

    /***** INTERNET GATEWAY *****/

    public function createInternetGateway(string $name): bool|Result
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            return $this->Ec2Client->createInternetGateway([
                'DryRun' => $isDryRun,
                'TagSpecifications' => [
                    [
                        'ResourceType' => 'internet-gateway',
                        'Tags' => [
                            ['Key' => 'Name', 'Value' => $name],
                            ['Key' => 'Description', 'Value' => 'Created by Uxmal Devtools Version '.$this->getDevtoolsVersion()],
                        ],
                    ],
                ],
            ]);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createInternetGateway] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function internetGatewayExists($igwId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeInternetGateways([
                'DryRun' => $isDryRun,
            ]);

            foreach ($result['InternetGateways'] as $igw) {
                if ($igw['InternetGatewayId'] === $igwId) {
                    return true; // IGW ID found
                }
            }

            return false; // IGW ID not found
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[internetGatewayExists] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    public function isInternetGatewayAttachedToVpc($vpcId, $igwId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeInternetGateways([
                'DryRun' => $isDryRun,
            ]);

            foreach ($result['InternetGateways'] as $igw) {
                if ($igw['InternetGatewayId'] === $igwId) {
                    foreach ($igw['Attachments'] as $attachment) {

                        if ($attachment['VpcId'] === $vpcId && $attachment['State'] === 'available') {
                            return true; // IGW is attached to the specified VPC
                        }
                    }
                }
            }
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[isInternetGatewayAttachedToVpc] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function attachInternetGatewayToVpc($igwId, $vpcId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $this->Ec2Client->attachInternetGateway([
                'InternetGatewayId' => $igwId,
                'VpcId' => $vpcId,
                'DryRun' => $isDryRun,
            ]);

            return true;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[attachInternetGatewayToVpc] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    /*********** ROUTE TABLES ***********/

    public function createRouteTable(string $vpcId, string $name, array $routes = []): string|false
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->createRouteTable([
                'VpcId' => $vpcId,
            ]);

            $routeTableId = $result['RouteTable']['RouteTableId'];

            $this->Ec2Client->createTags([
                'Resources' => [$routeTableId],
                'Tags' => [
                    [
                        'Key' => 'Name',
                        'Value' => $name,
                    ],
                ],
            ]);

            foreach ($routes as $cidr => $gwId) {
                $this->addRouteToRouteTable($routeTableId, $cidr, $gwId);
            }

            return $routeTableId;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createRouteTable] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function routeTableExists($routeTableId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            $result = $this->Ec2Client->describeRouteTables([
                'RouteTableIds' => [$routeTableId],
                'DryRun' => $isDryRun,
            ]);

            return ! empty($result['RouteTables']);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[routeTableExists] Dry run successful. You have the necessary permissions.');
            }
        } catch (Exception $e) {

        }

        return false;
    }

    public function setRouteTableIdToSubnet($subnetId, $routeTableId): Result|false
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $currentAssociation = $this->getSubnetAssociationKey($subnetId, 'RouteTableAssociationId');

            if ($currentAssociation) {
                $this->Ec2Client->disassociateRouteTable([
                    'AssociationId' => $currentAssociation,
                ]);
            }

            $result = $this->Ec2Client->associateRouteTable([
                'SubnetId' => $subnetId,
                'RouteTableId' => $routeTableId,
                'DryRun' => $isDryRun,
            ]);

            $associationId = $result['AssociationId'];
            $this->info("Subnet (ID: $subnetId) associated with route table (ID: $routeTableId) successfully. Association ID: $associationId");

            return $result;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[setRouteTableIdToSubnet] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function getRouteTablesForVpc($vpcId): bool|Result
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            return $this->Ec2Client->describeRouteTables([
                'DryRun' => $isDryRun,
                'Filters' => [
                    ['Name' => 'vpc-id', 'Values' => [$vpcId]],
                ],
            ]);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[getRouteTablesForVpc] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function setRouteTableName($routeTableId, $name): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            $this->Ec2Client->createTags([
                'Resources' => [$routeTableId],
                'Tags' => [
                    [
                        'Key' => 'Name',
                        'Value' => $name,
                    ],
                ],
            ]);

            return true;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[setRouteTableName] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function addRouteToRouteTable($routeTableId, $destinationCidrBlock, $gatewayId): bool|Result
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            return $this->Ec2Client->createRoute([
                'RouteTableId' => $routeTableId,
                'DestinationCidrBlock' => $destinationCidrBlock,
                'GatewayId' => $gatewayId,
            ]);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[addRouteToRouteTable] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    /**************** NAT GATEWAY ****************/

    public function elasticIpExists($allocationId)
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeAddresses([
                'AllocationIds' => [$allocationId],
            ]);

            return ! empty($result['Addresses']);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createEipAlloc] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    public function createEipAlloc(string $name = '', string $domain = 'vpc'): string|bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $tagSpecifications = [
                [
                    'ResourceType' => 'elastic-ip',
                    'Tags' => [
                        ['Key' => 'Name', 'Value' => $name],
                        ['Key' => 'Description', 'Value' => 'Created by Uxmal Devtools Version '.$this->getDevtoolsVersion()],
                    ],
                ],
            ];

            $result = $this->Ec2Client->allocateAddress([
                'Domain' => $domain,
                'DryRun' => $isDryRun,
                'TagSpecifications' => $tagSpecifications,
            ]);

            return $result['AllocationId'];
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createEipAlloc] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function createNatGateway($subnetId, $allocationId, $name): string|bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            $tagSpecifications = [
                [
                    'ResourceType' => 'natgateway',
                    'Tags' => [
                        ['Key' => 'Name', 'Value' => $name],
                        ['Key' => 'Description', 'Value' => 'Created by Uxmal Devtools Version '.$this->getDevtoolsVersion()],
                    ],
                ],
            ];

            $result = $this->Ec2Client->createNatGateway([
                'SubnetId' => $subnetId,
                'AllocationId' => $allocationId,
                'DryRun' => $isDryRun,
                'TagSpecifications' => $tagSpecifications,
            ]);

            return $result['NatGateway']['NatGatewayId'];
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createNatGateway] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function isNatGwRouteOnRouteTable($routeTableId, $natGwId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeRouteTables([
                'RouteTableIds' => [$routeTableId],
            ]);

            foreach ($result['RouteTables'] as $routeTable) {
                foreach ($routeTable['Routes'] as $route) {
                    if (isset($route['NatGatewayId']) && $route['NatGatewayId'] === $natGwId) {
                        return true; // Route to the specified NAT Gateway exists in the route table
                    }
                }
            }
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[isNatGwRouteOnRouteTable] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function isInternetGatewayRouteOnRouteTable($routeTableId, $igwId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';

        try {
            $result = $this->Ec2Client->describeRouteTables([
                'RouteTableIds' => [$routeTableId],
            ]);

            foreach ($result['RouteTables'] as $routeTable) {
                foreach ($routeTable['Routes'] as $route) {
                    if (isset($route['GatewayId']) && $route['GatewayId'] === $igwId) {
                        $this->info("Route to IGW $igwId found in route table $routeTableId.");

                        return true; // Route to the specified IGW exists in the route table
                    }
                }
            }
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[isInternetGatewayRouteOnRouteTable] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function internetNatGatewayExists($natGwId, $available = true): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            $result = $this->Ec2Client->describeNatGateways([
                'NatGatewayIds' => [$natGwId],
            ]);

            // Check if the NAT Gateway exists and is not in 'deleted' state
            foreach ($result['NatGateways'] as $natGateway) {
                $natGatewayState = $natGateway['State'];
                if ($available) {
                    if ($natGatewayState === 'available') {
                        return true; // NAT Gateway is available
                    }
                } else {
                    if ($natGatewayState !== 'deleted') {
                        return true; // NAT Gateway exists and is not deleted
                    }
                }
            }

            return false;  // NAT Gateway does not exist or is deleted
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[isInternetGatewayRouteOnRouteTable] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    public function createVPCE($name, $service, $vpcId, array $routeTables = [])
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            // Preparing the tag specifications
            $tagSpecifications = [
                [
                    'ResourceType' => 'vpc-endpoint',
                    'Tags' => [['Key' => 'Name', 'Value' => $name]],
                ],
            ];

            // Create VPC Endpoint
            $result = $this->Ec2Client->createVpcEndpoint([
                'VpcEndpointType' => 'Gateway', // or 'Gateway' based on the type of service
                'ServiceName' => $service,
                'VpcId' => $vpcId,
                'RouteTableIds' => $routeTables,
                'TagSpecifications' => $tagSpecifications,
            ]);

            return $result['VpcEndpoint']['VpcEndpointId'];
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createVPCE] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function vpceIdExists($vpceId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            // Describe the VPC Endpoints with the specified ID
            $result = $this->Ec2Client->describeVpcEndpoints([
                'VpcEndpointIds' => [$vpceId],
            ]);

            // Check if any endpoints were returned
            return ! empty($result['VpcEndpoints']);
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createVPCE] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    public function setVPCEToRouteTables($vpceId, array $routeTableIds): bool
    {
        $this->initEC2Client(); // Initialize your EC2 client
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            foreach ($routeTableIds as $routeTableId) {
                // Associate each route table with the VPC Endpoint
                $this->Ec2Client->replaceRoute([
                    'RouteTableId' => $routeTableId,
                    'DestinationCidrBlock' => '0.0.0.0/0', // Example CIDR, adjust as necessary
                    'VpcEndpointId' => $vpceId,
                ]);
            }

            return true;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[setVPCEToRouteTables] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }

    public function isRoutePresentInRouteTable($routeTableId, $destinationCidr, $gatewayId): bool
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        try {
            // Retrieve the route table information
            $result = $this->Ec2Client->describeRouteTables([
                'RouteTableIds' => [$routeTableId],
            ]);

            // Checking if the desired route with the specified gateway is present
            foreach ($result['RouteTables'] as $routeTable) {
                foreach ($routeTable['Routes'] as $route) {
                    if (array_key_exists('GatewayId', $route)) {
                        if ($route['DestinationCidrBlock'] === $destinationCidr && $route['GatewayId'] === $gatewayId) {
                            // Route with the specified gateway found
                            return true;
                        }
                    }
                    if (array_key_exists('NatGatewayId', $route)) {
                        if ($route['DestinationCidrBlock'] === $destinationCidr && $route['NatGatewayId'] === $gatewayId) {
                            // Route with the specified gateway found
                            return true;
                        }
                    }
                }
            }
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[createVPCE] Dry run successful. You have the necessary permissions.');
            }
        }

        return false;
    }

    public function getAvailabilityZones(): array|string
    {
        $this->initEC2Client();
        $isDryRun = $this->option('dry-run') !== 'false';
        if (isset($this->availabilityZones)) {
            return $this->availabilityZones;
        }
        try {
            $this->availabilityZones = $this->Ec2Client->describeAvailabilityZones()->toArray();
            $idx = 1;
            foreach ($this->availabilityZones['AvailabilityZones'] as $az) {
                $this->availabilityZones['az'.$idx] = $az['ZoneName'];
                $idx++;
            }

            return $this->availabilityZones;
        } catch (AwsException $e) {
            if ($isDryRun && $e->getAwsErrorCode() === 'DryRunOperation') {
                $this->info('[isInternetGatewayRouteOnRouteTable] Dry run successful. You have the necessary permissions.');
            } else {
                $this->error('Error: '.$e->getMessage());
            }
        }

        return false;
    }
}
