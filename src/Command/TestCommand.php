<?php

namespace Uxmal\Devtools\Command;

use Aws\Ec2\Ec2Client;
use Random\RandomException;
use Uxmal\Devtools\Command\Aws\AwsCommand;
use Uxmal\Devtools\Traits\GeneralUtils;

class TestCommand extends AwsCommand
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'devtools:test';

    /**
     * @var string The name and signature of the console command.
     */
    protected $signature = 'devtools:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the package.';

    /**
     * @var array|mixed
     */
    protected array $availabilityZones;

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
        $this->configureAWSDryRun();
    }

    /**
     * Execute the console command.
     *
     * @throws RandomException
     */
    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }

        $this->test_0();
    }

    protected function test_0(): void
    {
        $ec2Client = new Ec2Client([
            'version' => 'latest',
            'region' => $this->awsCredentialsRegion, // Usar la región desde .env
            'credentials' => [
                'key' => $this->awsCredentialsAccessKey, // Access key desde .env
                'secret' => $this->awsCredentialsSecretKey, // Secret key desde .env
            ],
        ]);

        print_r($ec2Client->describeVpcs(['VpcIds' => ['vpc-025e3bcb7bdd6d8d9']]));
    }

    /**
     * Install the package.
     */
    protected function test_1(): void
    {
        exit(0);
        $config_file = __DIR__.'/../../aws/nginx-php-fpm.php';

        $app_name = config('aws-infra.app.name');
        $app_domain = config('aws-infra.app.domain');
        $app_subdomain = config('aws-infra.app.subdomain');

        $app_prefix = config('aws-infra.app.prefix');
        $app_key = config('aws-infra.app.key');
        $app_vpc_network = config('aws-infra.app.vpc_network');
        $app_vpc_netmask = config('aws-infra.app.vpc_netmask');
        $app_vpc_subnet_netmask = config('aws-infra.app.vpc_subnet_netmask');

        $ecr_nginx_image = config('aws-infra.ecr.nginx.image', '707660622854.dkr.ecr.us-west-2.amazonaws.com/app-nginx-workshop');
        $ecr_nginx_tag = config('aws-infra.ecr.nginx.tag', 'dev-747dfd');
        $ecr_php_fpm_image = config('aws-infra.ecr.php-fpm.image', '707660622854.dkr.ecr.us-west-2.amazonaws.com/app-php-fpm-workshop');
        $ecr_php_fpm_tag = config('aws-infra.ecr.php-fpm.tag', 'dev-747dfd');

        $headers = ['Variable', 'Valor'];
        $variablesTable = [
            ['name', $app_name],
            ['prefix', $app_prefix],
            ['key', $app_key],
            ['domain', $app_domain],
            ['subdomain', $app_subdomain],
            ['vpc_network', $app_vpc_network],
            ['vpc_netmask', $app_vpc_netmask],
            ['vpc_subnet_netmask', $app_vpc_subnet_netmask],
            ['ecr_nginx_image', $ecr_nginx_image],
            ['ecr_nginx_tag', $ecr_nginx_tag],
            ['ecr_php_fpm_image', $ecr_php_fpm_image],
            ['ecr_php_fpm_tag', $ecr_php_fpm_tag],
        ];

        $this->table($headers, $variablesTable);

        $this->info('Creando archivo de configuración... config/aws-infra.php');

        $search = [
            '@app.name@',
            '@app.prefix@',
            '@app.key@',
            '@app.domain@',
            '@app.subdomain@',
            '@app.vpc_network@',
            '@app.vpc_netmask@',
            '@app.vpc_subnet_netmask@',
            '@ecr.nginx.image@',
            '@ecr.nginx.tag@',
            '@ecr.php-fpm.image@',
            '@ecr.php-fpm.tag@',
            '@aws.region@',
        ];

        $replace = [
            $app_name,
            $app_prefix,
            $app_key,
            $app_domain,
            $app_subdomain,
            $app_vpc_network,
            $app_vpc_netmask,
            $app_vpc_subnet_netmask,
            $ecr_nginx_image,
            $ecr_nginx_tag,
            $ecr_php_fpm_image,
            $ecr_php_fpm_tag,
            $this->awsCredentialsRegion,
        ];

        $subnets = $this->generateSubnets($app_vpc_network, $app_vpc_netmask, $app_vpc_subnet_netmask);
        foreach ($subnets as $index => $subnet) {
            $search[] = '@app.next_vpc_subnet_network_'.($index + 1).'@';
            $replace[] = $subnet;
        }

        $infra_template = __DIR__.'/Aws/Stubs/ecs-php-fpm-task-definition.json';

        $config = file_get_contents($infra_template);
        $config = str_replace($search, $replace, $config);

        $this->createCWLogGroup('/ecs/personalizalo-workshop-php-fpm-ecs-task');
        echo $config;

    }

    private function replaceAppName(string $appName, string $template): string
    {
        return str_replace('{uxdt:app.name}', $appName, $template);
    }
}
