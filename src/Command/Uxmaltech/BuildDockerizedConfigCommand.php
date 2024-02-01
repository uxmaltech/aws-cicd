<?php

namespace Uxmal\Devtools\Command\Uxmaltech;

use Illuminate\Console\Command;
use Random\RandomException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Traits\DockerUtils;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildDockerizedConfigCommand extends Command
{
    use DockerUtils, GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'devtools:build-dockerized-config';

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
        } catch (RandomException $e) {
            $this->warn('An error occurred: '.$e->getMessage());
        }
        system('clear');
        $this->buildDockerizedConfig();
    }

    private function buildDockerizedConfig(): void
    {

        $php_fpm_image = $this->getPHPFpmImage();
        $nginx_image = $this->getNginxImage();
        $apache_image = $this->getApacheImage();
        $php_cli_image = $this->getPhpCliImage();

        $this->info('Build Dockerized Config (dockerized.php)...');

        $this->newLine();

        $app_name = config('uxmaltech.name', config('APP_NAME', 'laravel'));

        $headers = ['Variable', 'Valor'];
        $variablesTable = [
            ['UXMALTECH_APP_NAME', $app_name],
            ['UXMALTECH_AUTHOR_NAME', config('uxmaltech.author.name', config('APP_AUTHOR', 'UxmalTech'))],
            ['UXMALTECH_AUTHOR_EMAIL', config('uxmaltech.author.email', config('APP_AUTHOR_EMAIL', '<info@uxmal.tech>'))],
            ['PHP_VERSION', $this->phpVersion],
            ['NGINX_VERSION', $this->nginxVersion],
            ['APACHE_VERSION', $this->apacheVersion],
            ['ALPINE_VERSION', $this->alpineVersion],
            ['PHP_FPM_IMAGE', $php_fpm_image],
            ['NGINX_IMAGE', $nginx_image],
            ['APACHE_IMAGE', $apache_image],
            ['PHP_CLI_IMAGE', $php_cli_image],
        ];

        $this->table($headers, $variablesTable);

    }
}
