<?php

namespace Uxmal\Devtools\Command\Docker;

use Illuminate\Console\Command;
use Random\RandomException;
use Uxmal\Devtools\Traits\GeneralUtils;

class ComposeUpCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'docker:compose-up 
                {--profile= : The profile to use}';


    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    /**
     * @throws RandomException
     */
    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            $this->error('Environment not ready.');
            exit(1);
        }
        system('clear');
        $this->upDockerCompose();
    }

    public function upDockerCompose(): void
    {
        $this->info('Running docker-compose up');

        $dockerImages = config('dockerized.images');

        $profile = $this->option('profile');
        if ($profile) {
            $this->info('Using profile: ' . $profile);
        } else {
            $this->info('Using default profile');
        }

        $compose = config('dockerized.compose');

        $compose_data_services = [];
        foreach ($compose as $service => $config) {
            if (!$profile) {
                $profile = $service;
            }
            switch ($config['type']) {
                case 'nginx-php-fpm':
                    $this->info('Configuring Services: ' . $service);
                    $nginx = null;
                    $phpFpm = null;
                    foreach ($config['services'] as $element => $data) {
                        switch ($element) {
                            case 'nginx':
                                $this->info('Starting service: ' . $element);
                                $nginx = $data;
                                break;
                            case 'php-fpm':
                                $this->info('Starting service: ' . $element);
                                $phpFpm = $data;
                                break;
                            default:
                                $this->error('Service not found: ' . $element);
                                exit(1);
                        }
                    }

                    if (!$nginx || !$phpFpm) {
                        $this->error('Must be two services nginx and php-fpm');
                        exit(1);
                    }

                    if (!array_key_exists('image', $nginx)) {
                        $this->error('Image not found for service: nginx');
                        exit(1);
                    }
                    if (!array_key_exists('image', $phpFpm)) {
                        $this->error('Image not found for service: php-fpm');
                        exit(1);
                    }

                    $nginxImage = $nginx['image'];
                    $phpFpmImage = $phpFpm['image'];

                    if (!array_key_exists('ports', $nginx)) {
                        $this->error('Ports not found for service: nginx');
                        exit(1);
                    }
                    if (!array_key_exists('ports', $phpFpm)) {
                        $this->error('Ports not found for service: php-fpm');
                        exit(1);
                    }

                    $vars = [];
                    $vars['NGINX_PORTS'] = $nginx['ports'];
                    $vars['NGINX_IMAGE_TAG'] = $nginx['image'];
                    $vars['NGINX_SERVICE_DN'] = $nginx['hostname'];

                    $vars['PHP_FPM_PORTS'] = $phpFpm['ports'];
                    $vars['PHP_FPM_IMAGE_TAG'] = $phpFpm['image'];
                    $vars['PHP_FPM_SERVICE_DN'] = $phpFpm['hostname'];

                    $vars['API_END_POINT_DN'] = $config['api_end_point'];

                    if (is_file(__DIR__ . '/docker-compose/nginx-php-fpm.stub')) {
                        $compose_data_services[] = str_replace(array_keys($vars), array_values($vars), file_get_contents(__DIR__ . '/docker-compose/nginx-php-fpm.stub'));
                    }
                    break;
                case 'octane':
                    $vars = [];
                    $vars['OCTANE_SERVICE_DN'] = $config['hostname'];
                    $vars['OCTANE_IMAGE_TAG'] = $config['image'];
                    $vars['OCTANE_PORTS'] = $config['ports'];
                    if (is_file(__DIR__ . '/docker-compose/nginx-php-fpm.stub')) {
                        $compose_data_services[] = str_replace(array_keys($vars), array_values($vars), file_get_contents(__DIR__ . '/docker-compose/octane.stub'));
                    }
                    break;
                case 'php-cli':
                    $vars = [];
                    $file_to_load = __DIR__ . '/docker-compose/php-cli.stub';
                    if (isset($config['hostname']) && isset($config['ports'])) {
                        $vars['PHP_CLI_SERVICE_DN'] = $config['hostname'];
                        $vars['PHP_CLI_PORTS'] = $config['ports'];
                        $file_to_load = __DIR__ . '/docker-compose/php-cli-network.stub';
                    }
                    $vars['PHP_CLI_IMAGE_TAG'] = $config['image'];
                    $vars['XXX_PHP_CLI_ARTISAN_COMMAND'] = $config['artisan-command'];
                    if (is_file(__DIR__ . '/docker-compose/php-cli.stub')) {
                        $compose_data_services[] = str_replace(array_keys($vars), array_values($vars), file_get_contents($file_to_load));
                    }
                    break;
                default :
                    $this->error('Service not found: ' . $service);
                    exit(1);
            }
        }

        $composeFilePath = tempnam(sys_get_temp_dir(), 'docker-compose-nginx-php-fpm');
        $composeFileServices = join("\n", $compose_data_services);
        $composeFileData = <<<EOT
version: '3.2'
services:
$composeFileServices
networks:
    uxmal-tech-network:
        driver: bridge
EOT;
        $this->warn('Creating temporary docker-compose file: ' . "\n" . $composeFileData);
        file_put_contents($composeFilePath, $composeFileData);
        $this->runDockerCmd(['compose', '-f', $composeFilePath, 'up']);

    }
}
