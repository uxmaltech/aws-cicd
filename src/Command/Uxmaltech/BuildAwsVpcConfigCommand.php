<?php

namespace Uxmal\Devtools\Command\Uxmaltech;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Enum\VpcTypeEnum;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildAwsVpcConfigCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'devtools:build-aws-vpc-config';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    public function handle(): void
    {
        try {
            if ($this->checkEnvironment() === false) {
                exit(1);
            }
        } catch (ProcessFailedException $exception) {
            $this->warn('An error occurred: '.$exception->getMessage());
        }
        system('clear');
        $this->buildAwsVpcConfig();
    }

    private function buildAwsVpcConfig(): void
    {
        $this->info('Creando (los) archivo de configuración (AWS-VPC)...');

        $options = VpcTypeEnum::cases();
        $vpc_type = $this->choice(
            'Escoje el tipo de VPC (subredes)',
            array_map(fn ($option) => $option->value, $options),
            $defaultIndex = 0);

        switch ($vpc_type) {
            case VpcTypeEnum::pub2_priv2_nat2->value:
                $vpc_template_file = __DIR__.'/Stubs/tmpl-aws-vpc-2pub-2nat-2priv.stub';
                break;
            case VpcTypeEnum::pub2_priv1_nat1->value:
                $vpc_template_file = __DIR__.'/Stubs/tmpl-aws-vpc-2pub-1nat-1priv.stub';
                break;
            default:
                $this->error('Opción no implementada');
                exit(1);
        }

        $app_vpc_network = $this->ask('Red de la VPC', '192.168.0.0');
        $app_vpc_netmask = $this->ask('Máscara de red de la VPC', 20);
        $app_vpc_subnet_netmask = $this->ask('Máscara para segementación de subredes', 25);

        $headers = ['Variable', 'Valor'];
        $variablesTable = [
            ['vpc_network', $app_vpc_network],
            ['vpc_netmask', $app_vpc_netmask],
            ['vpc_subnet_netmask', $app_vpc_subnet_netmask],
        ];

        $this->table($headers, $variablesTable);
        if (! $this->confirm('¿Es correcto?')) {
            $this->error('Abortando...');
            exit(1);
        }

        $search = [
            '@app.name@',
            '@app.prefix@',
            '@app.key@',
            '@app.domain@',
            '@app.subdomain@',
            '@app.internal_domain@',
            '@app.mode@',
            '@app.service.ports@',
            '@vpc.network@',
            '@vpc.netmask@',
            '@vpc.subnet.netmask@',
        ];

        $replace = [
            config('uxmaltech.name', config('APP_NAME', 'laravel')),
            config('uxmaltech.prefix', 'uxdt'),
            config('uxmaltech.key', config('app.key')),
            config('uxmaltech.domain', config('app.domain')),
            config('uxmaltech.subdomain', config('app.subdomain')),
            config('uxmaltech.internal_domain', config('app.internal_domain')),
            config('uxmaltech.mode', config('app.mode')),
            $this->var_export_short(config('uxmaltech.service_ports'), true),
            $app_vpc_network,
            $app_vpc_netmask,
            $app_vpc_subnet_netmask,
        ];

        $subnets = $this->generateSubnets($app_vpc_network, $app_vpc_netmask, $app_vpc_subnet_netmask);
        foreach ($subnets as $index => $subnet) {
            $search[] = '@app.next_vpc_subnet_network_'.($index + 1).'@';
            $replace[] = $subnet;
        }

        $config = file_get_contents($vpc_template_file);
        $config = str_replace($search, $replace, $config);

        file_put_contents(config_path('aws-vpc.php'), $config);
    }
}
