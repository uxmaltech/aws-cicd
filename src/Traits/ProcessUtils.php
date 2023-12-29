<?php

namespace Uxmal\AwsCICD\Traits;

use Illuminate\Support\Str;
use InvalidArgumentException;
use MCStreetguy\ComposerParser\Factory as ComposerParser;
use Mockery\Exception;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait ProcessUtils
{
    protected string $alpineVersion;
    protected string $phpVersion;
    protected string $imagesUsername;
    protected string $imagesTag;


    protected string $nginxAlpineVersion;
    protected string $nginxVersion;
    protected string $nginxUsername;
    protected string $nginxTag;

    protected string $phpFpmAlpineVersion;
    protected string $phpFpmVersion;
    protected string $phpFpmUsername;
    protected string $phpFpmTag;

    protected string $awsEcsServicePhpFpmClusterName;
    protected int $awsEcsServicePhpFpmClusterPort;
    protected string $awsEcsServiceNginxClusterName;
    protected int $awsEcsServiceNginxClusterPort;

    protected int $awsEcsServicePort = 80;

    protected string $awsEcrRepositoryUsername;


    protected string $gitTag = 'none';
    protected string $gitBranch = 'none';
    protected string $gitCommit = 'none';

    protected string $laravelAppTag = 'latest';
    protected string $laravelAppTimeZone = 'America/Monterrey';
    protected string $laravelAppAuthor = '';
    protected string $laravelAppEmail = '';
    protected string $laravelAppName = 'laravel-app';
    protected string $laravelAppEnv = 'production';
    protected string $laravelAppKey = '';
    protected bool $laravelAppDebug = false;
    protected string $laravelAppUrl = 'http://localhost';


    protected array $uxmalPrivatePackages = [
        'uxmaltech/backoffice-ui',
        'uxmal/backoffice-ui',
    ];

    protected bool $needPersonalAccessToken = false;

    protected null|string $personalAccessToken = null;

    protected array $pathRepositoriesToCopy = [];

    function checkEnvironment(): bool
    {
        if (!file_exists($this->laravel->basePath('docker-images')) || !file_exists($this->laravel->configPath('aws-cicd.php'))) {
            $this->error('Docker images folder not found. Please run "php artisan aws-cicd:install" first.');
            return false;
        }

        if (!$this->isCommandAvailable('docker')) {
            $this->error('Docker is not available. Please install Docker first.');
            return false;
        }

        if (!$this->isCommandAvailable('git')) {
            $this->warn('Git is not available. Please install Git first.');
        }

        $this->alpineVersion = config('aws-cicd.images.alpineVersion', '3.19');
        $this->phpVersion = config('aws-cicd.images.phpVersion', '0.2');
        $this->imagesUsername = config('aws-cicd.images.username', 'uxmaltech');
        $this->imagesTag = config('aws-cicd.images.tag', 'latest');


        $this->nginxAlpineVersion = config('aws-cicd.dockerized.containers.nginx.alpineVersion', '3.19');
        $this->nginxVersion = config('aws-cicd.dockerized.containers.nginx.nginxVersion', '1.21');
        $this->nginxUsername = config('aws-cicd.dockerized.containers.nginx.username', 'uxmaltech');
        $this->nginxTag = config('aws-cicd.dockerized.containers.nginx.tag', 'latest');

        $this->phpFpmAlpineVersion =config('aws-cicd.dockerized.containers.php-fpm.alpineVersion', '3.19');
        $this->phpFpmVersion = config('aws-cicd.dockerized.containers.php-fpm.phpVersion', '8.2');
        $this->phpFpmUsername = config('aws-cicd.dockerized.containers.php-fpm.username', 'uxmaltech');
        $this->phpFpmTag = config('aws-cicd.dockerized.containers.php-fpm.tag', 'latest');

        $this->awsEcsServicePhpFpmClusterName = config('aws-cicd.aws.ecs.service.php-fpm.cluster_name', 'php-fpm-cluster-ecs');
        $this->awsEcsServicePhpFpmClusterPort = config('aws-cicd.aws.ecs.service.php-fpm.cluster_port', 9000);
        $this->awsEcsServiceNginxClusterName = config('aws-cicd.aws.ecs.service.nginx.cluster_name', 'nginx-cluster-ecs');
        $this->awsEcsServiceNginxClusterPort = config('aws-cicd.aws.ecs.service.nginx.cluster_port', 8000);
        $this->awsEcsServicePort = config('aws-cicd.aws.ecs.service.port', 80);

        $this->awsEcrRepositoryUsername = Str::snake(config('aws-cicd.aws.ecr.repository.username', $this->laravelAppAuthor));

        $this->laravelAppAuthor = config('aws-cicd.laravel.app.author', 'Author');
        $this->laravelAppEmail = config('aws-cicd.laravel.app.email', 'author@email.com');
        $this->laravelAppName = config('aws-cicd.laravel.app.name', 'laravel-app');
        $this->laravelAppEnv = config('aws-cicd.laravel.app.env', 'production');
        $this->laravelAppKey = config('aws-cicd.laravel.app.key', 'base64:'.base64_encode(Str::random(32)));
        $this->laravelAppDebug = config('aws-cicd.laravel.app.debug', false);
        $this->laravelAppUrl = config('aws-cicd.laravel.app.url', 'http://localhost');

        $this->laravelAppTimeZone = config('aws-cicd.laravel.app.timezone', 'America/Monterrey');
        if (is_dir($this->laravel->basePath('.git'))) {
            $this->gitTag = $this->runCmd(['git', 'describe', '--tags', '--exact-match', '2>', '/dev/null']);
            $this->gitBranch = $this->runCmd(['git', 'symbolic-ref', '-q', '--short', 'HEAD']);
            $this->gitCommit = $this->runCmd(['git', 'rev-parse', '--short', 'HEAD']);

            if( $this->gitTag ){
                $this->laravelAppTag = $this->gitTag;
            } else {
                $this->laravelAppTag = (($this->gitBranch) ? $this->gitBranch.'-' : '' ).(($this->gitCommit) ? $this->gitCommit : '' );
            }
        }

        $this->info("====================== Environment ======================");
        $this->info("App Tag: $this->laravelAppTag");
        $this->info("App Timezone: $this->laravelAppTimeZone");
        $this->info("Nginx Cluster Name: $this->awsEcsServiceNginxClusterName");
        $this->info("Nginx Cluster Port: $this->awsEcsServiceNginxClusterPort");
        $this->info("PHP-FPM Cluster Name: $this->awsEcsServicePhpFpmClusterName");
        $this->info("PHP-FPM Cluster Port: $this->awsEcsServicePhpFpmClusterPort");

        return true;
    }

    function isCommandAvailable($command): bool
    {
        // Determine the right command to check availability
        $checkCommand = (PHP_OS_FAMILY === 'Windows') ? "where" : "which";

        // Create the process
        $process = new Process([$checkCommand, $command]);
        $process->run();

        // Check if the process was successful
        if (!$process->isSuccessful()) {
            return false;
        }

        // The command exists if the output is not empty
        return $process->getOutput() !== '';
    }

    public function runProcess(string $command, string $cwd = null, array $env = null, ?int $timeout = 60): string
    {
        $process = Process::fromShellCommandline($command, $cwd, $env, null, $timeout);

        if (file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    public function checkProtectedPackages(): void
    {
        try {
            $composerJson = ComposerParser::parse($this->laravel->basePath('composer.json'));
            $require = $composerJson->getRequire();

            foreach ($require as $requirement) {
                $package = $requirement['package'];
                $version = $requirement['version'];
                if (in_array($package, $this->uxmalPrivatePackages)) {
                    $this->info("Package: $package, Version: $version Need Personal Access Token.");
                    $this->needPersonalAccessToken = true;
                }
            }

            $requireDev = $composerJson->getRequireDev();

            foreach ($requireDev as $requirement) {
                $package = $requirement['package'];
                $version = $requirement['version'];
                if (in_array($package, $this->uxmalPrivatePackages)) {
                    $this->info("Package: $package, Version: $version Need Personal Access Token.");
                    $this->needPersonalAccessToken = true;
                }
            }

            if ($this->needPersonalAccessToken === true) {
                $this->personalAccessToken = config('aws-cicd.uxmaltech.personalAccessToken');
                if (empty($this->personalAccessToken)) {
                    $this->error('Personal Access Token is required. Please add UXMALTECH_PERSONAL_ACCESS_TOKEN to your .env file.');
                    exit(1);
                }

                // Check if added to composer global config composer config --global github-oauth.github.com
                try {
                    $composerGlobalAccessToken = $this->runCmd(['composer', 'config', '--global', 'github-oauth.github.com']);
                } catch (Exception $e) {
                    $composerGlobalAccessToken = '';
                }

                if (empty($composerGlobalAccessToken)) {
                    $this->info('Adding Personal Access Token to composer global config.');
                    $this->runCmd(['composer', 'config', '--global', '--auth', 'github-oauth.github.com', $this->personalAccessToken]);
                } else if ($composerGlobalAccessToken === $this->personalAccessToken) {
                    $this->info('Personal Access Token already added to composer global config.');
                } else {
                    $this->error('Personal Access Token already added to composer global config but is different.');
                    exit(1);
                }

            }


            $repositories = $composerJson->getRepositories();
            foreach ($repositories as $repository) {
                if ($repository->getType() === 'path') {
                    $this->pathRepositoriesToCopy[] = $repository->getUrl();
                }
            }

        } catch (InvalidArgumentException $e) {
            // The given file could not be found or is not readable
            $this->error('composer.json file not found.');
        } catch (RuntimeException $e) {
            // The given file contained an invalid JSON string
            $this->error('composer.json file is not valid.');
        }
    }

    function runCmd(array $args, array $envVars = []): string
    {
        $process = new Process($args);
        // Execute the process
        if (!empty($envVars)) {
            $process->setEnv($envVars);
        }
        $process->run();

        // Capture the output
        return trim($process->getOutput());


    }
}
