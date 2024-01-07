<?php

namespace Uxmal\Devtools\Traits;

use Closure;
use Illuminate\Support\Str;
use InvalidArgumentException;
use MCStreetguy\ComposerParser\Factory as ComposerParser;
use Mockery\Exception;
use Random\RandomException;
use RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait GeneralUtils
{
    protected string $configFile = 'aws-cicd.php';

    protected string $alpineVersion;

    protected string $phpVersion;

    protected string $imagesUsername;

    protected string $imagesTag;

    protected string $nginxUsername;

    protected string $phpFpmUsername;

    protected string $clusterContainerphpFpmTag;

    protected string $awsEcsServicePhpFpmClusterName;

    protected int $awsEcsServicePhpFpmClusterPort;

    protected string $awsEcsServiceNginxClusterName;

    protected int $awsEcsServiceNginxClusterPort;

    protected int $awsEcsServicePort = 80;

    protected array $awsEcrRepositories;

    protected string $awsCredentialsAccessKey;

    protected string $awsCredentialsSecretKey;

    protected string $awsCredentialsRegion;

    protected string $awsCredentialsToken;

    protected string $gitTag = 'none';

    protected string $gitBranch = 'none';

    protected string $gitCommit = 'none';

    protected string $ecrImageTag = 'latest';

    protected string $laravelAppTimeZone = 'America/Monterrey';

    protected string $laravelAppAuthor = '';

    protected string $laravelAppEmail = '';

    protected string $laravelAppName = 'laravel-app';

    protected string $laravelAppEnv = 'production';

    protected string $laravelAppKey = '';

    protected bool $laravelAppDebug = false;

    protected string $laravelAppUrl = 'http://localhost';

    /* NEW DEFINITIONS */

    protected bool $debugMode = false;

    protected bool $devMode = false;

    // Cluster
    protected string $clusterName;

    protected string $clusterPort;

    protected string $clusterFqdnDomain;

    protected string $clusterIntranetDomain;

    protected string $clusterIntranetSubdomain;

    // Cluster Services
    protected string $clusterNginxServiceHost;

    protected string $clusterPhpFpmServiceHost;

    // AWS
    protected ?string $clusterAwsVpcId;

    // Container Tags PHP-FPM (Base From)
    protected string $clusterContainerPhpFpmBaseImage;

    protected string $clusterContainerPhpFpmVersion;

    protected string $clusterContainerPhpFpmAlpineVersion;

    // Container Tags PHP-FPM (Base)
    protected string $clusterContainerPhpFpmPort;

    protected string $clusterContainerPhpFpmTag;

    protected string $clusterContainerPhpFpmRepository;

    protected string $clusterContainerPhpFpmRepositoryTag;

    // Container Tags PHP-FPM (Application)
    protected string $clusterContainerPhpFpmAppRepository;

    protected string $clusterContainerPhpFpmAppRepositoryTag;

    protected string $clusterContainerPhpFpmAppRepositoryTagLatest;

    // Container Tags NGINX (Base From)
    protected string $clusterContainerNginxPort;

    protected string $clusterContainerNginxBaseImage;

    protected string $clusterContainerNginxVersion;

    protected string $clusterContainerNginxAlpineVersion;

    // Container Tags NGINX (Base)
    protected string $clusterContainerNginxTag;

    protected string $clusterContainerNginxRepository;

    protected string $clusterContainerNginxRepositoryTag;

    // Container Tags NGINX
    protected string $clusterContainerNginxAppRepository;

    protected string $clusterContainerNginxAppRepositoryTag;

    protected string $clusterContainerNginxAppRepositoryTagLatest;

    /*  EO NEW DEFINITIONS */
    /**********************************************/

    protected array $uxmalPrivatePackages = [
        'uxmaltech/backoffice-ui',
    ];

    protected array $repositoriesKeys;

    protected bool $needPersonalAccessToken = false;

    protected ?string $personalAccessToken = null;

    protected array $pathRepositoriesToCopy = [];

    protected function configureSilentOption(): void
    {
        $this->getDefinition()->addOption(
            new InputOption(
                'silent', // Name of the option
                null, // Shortcut, if any
                InputOption::VALUE_NONE, // Option type
                'Run the command in silent mode' // Description
            )
        );
    }

    protected function configureAWSDryRun(): void
    {
        $this->getDefinition()->addOption(
            new InputOption(
                'dry-run', // Name of the option
                null, // Shortcut, if any
                InputOption::VALUE_OPTIONAL, // Option type
                'Run the aws commands in dry-run option (default: yes) to actually run in aws use --dry-run=false', // Description
                'true' // Default value
            )
        );
    }

    /**
     * @throws RandomException
     */
    public function checkEnvironment(): bool
    {
        $this->devMode = config('uxmaltech.dev_mode', false);

        if (! is_dir($this->laravel->basePath('docker-images'))) {
            $this->error('Docker images folder not found. Please run "php artisan devtools:install" first.');
            exit(0);
        }

        if (! file_exists($this->laravel->configPath('uxmaltech.php'))) {
            $this->error('config/uxmaltech.php file not found. Please run "php artisan devtools:install" first.');
            exit(0);
        }

        if ($this->hasPendingGitCommits($this->laravel->basePath()) && ! $this->devMode) {
            $this->warn('El directorio de trabajo tiene cambios pendientes. Por favor, utilize <comment>git commit</comment> o <comment>stash</comment>');
            if (! $this->confirm('¿Deseas continuar?', true)) {
                $this->error('Operación cancelada por el usuario.');
                exit(0);
            }

        }

        if (! $this->isCommandAvailable('docker')) {
            $this->error('Docker is not available. Please install Docker first.');
            exit(0);
        }

        if (! $this->isCommandAvailable('git')) {
            $this->error('Git is not available. Please install Git first.');
            exit(0);
        }

        if (! $this->isCommandAvailable('composer')) {
            $this->error('Composer is not available (composer binary). Please install Composer first.');
            exit(1);
        }

        if (is_dir($this->laravel->basePath('.git'))) {
            $this->gitTag = $this->runCmd(['git', 'describe', '--tags', '--exact-match', '2>', '/dev/null']);
            $this->gitBranch = $this->runCmd(['git', 'symbolic-ref', '-q', '--short', 'HEAD']);
            $this->gitCommit = $this->runCmd(['git', 'rev-parse', '--short', 'HEAD']);

            if ($this->gitTag) {
                $this->ecrImageTag = $this->gitTag;
            } else {
                $this->ecrImageTag = (($this->gitBranch) ? $this->gitBranch.'-' : '').(($this->gitCommit) ? $this->gitCommit : '');
            }
        }

        if ($this->devMode) {
            $this->ecrImageTag = 'dev-'.bin2hex(random_bytes(3));
        }

        /********** END CHECKS **********/

        $this->clusterName = config('aws-cicd.cluster.name', 'laravel-app');
        $this->clusterPort = config('aws-cicd.cluster.port', 80);

        $this->clusterFqdnDomain = config('aws-cicd.cluster.fqdn.domain', 'uxmal-devtools-aws.com');
        $this->clusterIntranetDomain = config('aws-cicd.cluster.intranet.domain', 'uxmal-devtools-aws.intranet');
        $this->clusterIntranetSubdomain = config('aws-cicd.cluster.intranet.subdomain', 'laravel-app');

        $this->clusterNginxServiceHost = 'nginx.'.$this->clusterIntranetSubdomain.'.'.$this->clusterIntranetDomain;
        $this->clusterPhpFpmServiceHost = 'php-fpm.'.$this->clusterIntranetSubdomain.'.'.$this->clusterIntranetDomain;

        $this->clusterContainerPhpFpmPort = config('aws-cicd.cluster.containers.php-fpm.port', 9000); // clusterContainerPhpFpmPort
        // Container PHP-FPM (Base From)
        $this->clusterContainerPhpFpmAlpineVersion = config('aws-cicd.dockerized.containers.php-fpm.alpineVersion', '3.19'); // phpFpmAlpineVersion
        $this->clusterContainerPhpFpmVersion = config('aws-cicd.dockerized.containers.php-fpm.phpVersion', '8.2');
        $this->clusterContainerPhpFpmBaseImage = 'php'.$this->clusterContainerPhpFpmVersion.'-fpm-alpine'.$this->clusterContainerPhpFpmAlpineVersion;

        // Container PHP-FPM (Base)
        $this->clusterContainerPhpFpmTag = config('aws-cicd.dockerized.containers.php-fpm.tag', 'latest');
        $this->clusterContainerPhpFpmRepository = 'base-php-fpm';
        $this->clusterContainerPhpFpmRepositoryTag = "$this->clusterContainerPhpFpmRepository:$this->clusterContainerPhpFpmTag";

        // Container PHP-FPM (Application)
        $this->clusterContainerPhpFpmAppRepository = "app-php-fpm-$this->clusterName";
        $this->clusterContainerPhpFpmAppRepositoryTag = "$this->clusterContainerPhpFpmAppRepository:$this->ecrImageTag";
        $this->clusterContainerPhpFpmAppRepositoryTagLatest = "$this->clusterContainerPhpFpmAppRepository:latest";

        $this->clusterContainerNginxPort = config('aws-cicd.cluster.containers.nginx.port', 80); // clusterContainerNginxPort
        // Container NGINX (Base From)
        $this->clusterContainerNginxAlpineVersion = config('aws-cicd.dockerized.containers.nginx.alpineVersion', '3.19');
        $this->clusterContainerNginxVersion = config('aws-cicd.dockerized.containers.nginx.nginxVersion', '1.24.0');
        $this->clusterContainerNginxBaseImage = "nginx-alpine$this->clusterContainerNginxAlpineVersion";

        // Container NGINX (Base)
        $this->clusterContainerNginxTag = config('aws-cicd.dockerized.containers.nginx.tag', 'latest'); // nginxTag
        $this->clusterContainerNginxRepository = 'base-nginx'; // nginxRepository
        $this->clusterContainerNginxRepositoryTag = "$this->clusterContainerNginxRepository:$this->clusterContainerNginxTag"; // nginxRepositoryTag

        // Container NGINX (Application)
        $this->clusterContainerNginxAppRepository = 'app-nginx-'.$this->clusterName;
        $this->clusterContainerNginxAppRepositoryTag = "$this->clusterContainerNginxAppRepository:$this->ecrImageTag";
        $this->clusterContainerNginxAppRepositoryTagLatest = "$this->clusterContainerNginxAppRepository:latest";

        $this->repositoriesKeys = [
            $this->clusterContainerPhpFpmRepository,
            $this->clusterContainerNginxRepository,
            $this->clusterContainerPhpFpmAppRepository,
            $this->clusterContainerNginxAppRepository,
        ];

        /*
         * EO Set Environment Variables
         */
        $this->alpineVersion = config('aws-cicd.images.alpineVersion', '3.19');
        $this->phpVersion = config('aws-cicd.images.phpVersion', '0.2');
        $this->imagesUsername = config('aws-cicd.images.username', 'uxmaltech');
        $this->imagesTag = config('aws-cicd.images.tag', 'latest');

        $this->nginxUsername = config('aws-cicd.dockerized.containers.nginx.username', 'uxmaltech');
        $this->phpFpmUsername = config('aws-cicd.dockerized.containers.php-fpm.username', 'uxmaltech');

        $this->awsEcsServicePhpFpmClusterName = config('aws-cicd.aws.ecs.service.php-fpm.cluster_name', 'php-fpm-cluster-ecs');
        $this->awsEcsServicePhpFpmClusterPort = config('aws-cicd.aws.ecs.service.php-fpm.cluster_port', 9000);
        $this->awsEcsServiceNginxClusterName = config('aws-cicd.aws.ecs.service.nginx.cluster_name', 'nginx-cluster-ecs');

        $this->awsEcsServicePort = config('aws-cicd.aws.ecs.service.port', 80);

        $this->awsCredentialsAccessKey = config('aws-cicd.aws.credentials.access_key', '');
        $this->awsCredentialsSecretKey = config('aws-cicd.aws.credentials.secret_key', '');
        $this->awsCredentialsRegion = config('aws-cicd.aws.credentials.region', '');

        $this->laravelAppAuthor = config('aws-cicd.laravel.app.author', 'Author');
        $this->laravelAppEmail = config('aws-cicd.laravel.app.email', 'author@email.com');
        $this->laravelAppName = strtolower(config('aws-cicd.laravel.app.name', 'laravel-app'));
        $this->laravelAppEnv = config('aws-cicd.laravel.app.env', 'production');
        $this->laravelAppKey = config('aws-cicd.laravel.app.key', 'base64:'.base64_encode(Str::random(32)));
        $this->laravelAppDebug = config('aws-cicd.laravel.app.debug', false);
        $this->laravelAppUrl = config('aws-cicd.laravel.app.url', 'http://localhost');

        $this->laravelAppTimeZone = config('aws-cicd.laravel.app.timezone', 'America/Monterrey');

        $this->awsEcrRepositories = config('aws-cicd.aws.ecr.repositories', []);

        if (empty($this->awsEcrRepositories)) {
            $this->awsEcrRepositories = [
                'base-php-fpm' => $this->phpFpmRepository ?? '',
                'base-nginx' => $this->clusterContainerNginxRepository,
                'app-php-fpm' => $this->clusterContainerPhpFpmAppRepository,
                'app-nginx' => $this->nginxAppRepository ?? '',
            ];
        }

        /*
                if (! $this->isSilent()) {
                    $this->info('====================== Environment ======================');
                    $this->warn("(app) Tag: $this->ecrImageTag");
                    $this->info("(app) Timezone: $this->laravelAppTimeZone");
                    $this->info("\n(php-fpm) Version: $this->clusterContainerPhpFpmVersion");
                    $this->info("(php-fpm) Alpine Version: $this->clusterContainerPhpFpmAlpineVersion");
                    $this->info("\n(nginx) Version: $this->clusterContainerNginxVersion");
                    $this->info("(nginx) Alpine Version: $this->clusterContainerNginxAlpineVersion");
                    $this->info("\n(base-image) php-fpm repository => $this->clusterContainerPhpFpmRepository");
                    $this->info("(base-image) php-fpm repository:tag $this->clusterContainerPhpFpmAppRepositoryTag");
                    $this->info("(base-image) nginx repository : $this->clusterContainerNginxRepository");
                    $this->info("(base-image) nginx repository:tag $this->clusterContainerNginxRepositoryTag");
                    $this->info("\n(app-image) php-fpm repository : $this->clusterContainerPhpFpmAppRepository");
                    $this->info("(app-image) php-fpm repository:tag $this->clusterContainerPhpFpmAppRepositoryTag");
                    $this->info("(app-image) nginx repository : $this->clusterContainerNginxAppRepository");
                    $this->info("(app-image) nginx repository:tag $this->clusterContainerNginxAppRepositoryTag");
                    $this->info("\n(ecs-nginx) Cluster Name: $this->awsEcsServiceNginxClusterName");
                    $this->info("(ecs-nginx) Cluster Port: $this->clusterContainerPhpFpmPort");
                    $this->info("\n(ecs-php) Cluster Name: $this->awsEcsServicePhpFpmClusterName");
                    $this->info("(ecs-php) Cluster Port: $this->awsEcsServicePhpFpmClusterPort");
                    $this->info('=========================================================');
                    $this->info("\n\n");
                }
        */
        return true;
    }

    public function isCommandAvailable($command): bool
    {
        // Determine the right command to check availability
        $checkCommand = (PHP_OS_FAMILY === 'Windows') ? 'where' : 'which';

        // Create the process
        $process = new Process([$checkCommand, $command]);
        $process->run();

        // Check if the process was successful
        if (! $process->isSuccessful()) {
            return false;
        }

        // The command exists if the output is not empty
        return $process->getOutput() !== '';
    }

    public function runProcess(string $command, ?string $cwd = null, ?array $env = null, ?int $timeout = 60): string
    {
        $process = Process::fromShellCommandline($command, $cwd, $env, null, $timeout);

        if (file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run();

        if (! $process->isSuccessful()) {
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
                    $this->line("$package:$version \t\t[<comment>Need Personal Access Token</comment>].");
                    $this->needPersonalAccessToken = true;
                }
            }

            $requireDev = $composerJson->getRequireDev();

            foreach ($requireDev as $requirement) {
                $package = $requirement['package'];
                $version = $requirement['version'];
                if (in_array($package, $this->uxmalPrivatePackages)) {
                    $this->line("$package:$version \t\t[<comment>Need Personal Access Token</comment>].");
                    $this->needPersonalAccessToken = true;
                }
                /*
                if( $package === 'nunomaduro/collision'){
                    $this->error("nunomaduro/collision must be in the required group not in required-dev.");
                    $this->warn("Please move nunomaduro/collision to the required group.");
                    $this->warn("composer remove 'nunomaduro/collision' --dev && composer require 'nunomaduro/collision'");
                    exit(1);
                }
                */
            }

            if ($this->needPersonalAccessToken === true) {
                $this->personalAccessToken = config('uxmaltech.git-hub-personal-access-token');
                if (empty($this->personalAccessToken)) {
                    $this->warn('Personal Access Token is required. Please add GITHUB_PERSONAL_ACCESS_TOKEN to your .env file.');
                }

                // Check if added to composer global config composer config --global github-oauth.github.com
                try {
                    $composerGlobalAccessToken = $this->runCmd(['composer', 'config', '--global', 'github-oauth.github.com']);
                } catch (Exception $e) {
                    $composerGlobalAccessToken = '';
                }

                if (empty($composerGlobalAccessToken)) {
                    $this->runCmd(['composer', 'config', '--global', '--auth', 'github-oauth.github.com', $this->personalAccessToken]);
                } elseif ($composerGlobalAccessToken === $this->personalAccessToken) {
                    $this->line('Personal Access Token already added.'."\t\t".'[<comment>OK</comment>]');
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

    public function hasPendingGitCommits($directory): bool
    {
        // Cambia al directorio de trabajo
        if (! is_dir($this->laravel->basePath().'/.git')) {
            return false;
        }
        $process = Process::fromShellCommandline('git status --porcelain', $directory);

        try {
            $process->mustRun();

            // Obtener la salida del comando
            $output = $process->getOutput();

            // Si la salida está vacía, no hay cambios pendientes
            return ! empty($output);
        } catch (ProcessFailedException $exception) {
            // Manejar la excepción si el proceso falla
            echo 'Error al ejecutar git status: ', $exception->getMessage();

            return false;
        }
    }

    public function runCmd(array $args, array $envVars = []): string
    {
        if ($this->devMode) {
            $this->newLine();
            $this->line("Running Command [bash -c <comment>'".implode(' ', $args)."'</comment>]");
        }
        $process = new Process($args);
        // Execute the process
        if (! empty($envVars)) {
            $process->setEnv($envVars);
        }
        $process->run();

        // Capture the output
        return trim($process->getOutput());
    }

    public function runComposerCmd(array $args, string $cwd = '.', array $envVars = []): string
    {

        $process = new Process(array_merge(['composer'], $args));
        // Execute the process

        $process->setTimeout(3600);
        $process->setIdleTimeout(60);

        if (! empty($cwd)) {
            $process->setWorkingDirectory($cwd);
        }

        $envVars += [
            'COMPOSER_ALLOW_SUPERUSER' => 1,
        ];

        if (! empty($envVars)) {
            $process->setEnv($envVars);
        }

        if ($this->devMode) {
            $this->info('Running Command [composer '.implode(' ', $args).']');
            if (file_exists('/dev/tty') && is_readable('/dev/tty')) {
                $process->setTty(true);
            }
        }
        $process->run();

        // Capture the output
        return $this->devMode ? true : trim($process->getOutput());
    }

    public function runDockerCmd(array $args, string $cwd = '.', mixed $input = null, array $envVars = []): string
    {

        $process = new Process(array_merge(['docker'], $args));

        $process->setTimeout(36000);
        $process->setIdleTimeout(3600);
        if (! empty($input)) {
            $process->setInput($input);
        }

        if (! empty($cwd)) {
            $process->setWorkingDirectory($cwd);
        }

        $envVars += [
            'BUILDKIT_PROGRESS' => 'plain',
            'DOCKER_DEFAULT_PLATFORM' => 'linux/amd64',
        ];
        // Execute the process
        if (! empty($envVars)) {
            $process->setEnv($envVars);
        }

        if ($this->devMode) {
            $this->info('Running Command [docker '.implode(' ', $args).']');
            foreach ($envVars as $key => $value) {
                $this->info("Running EnvVar: [$key => $value]");
            }
            if (file_exists('/dev/tty') && is_readable('/dev/tty')) {
                $process->setTty(true);
            }
        }

        try {
            $process->mustRun();
        } catch (ProcessFailedException $exception) {
            $this->error('An error occurred executing command: '.$process->getCommandLine());
            $this->error('Code:'.$process->getExitCode().' Description:'.$process->getExitCodeText());
            exit(0);
        }

        // Capture the output
        return $this->devMode ? true : trim($process->getOutput());
    }

    /**
     * Check if the silent option is set.
     */
    protected function isSilent()
    {
        return $this->option('silent');
    }

    protected function isDryRun(): bool
    {
        return $this->option('dry-run') !== 'false';
    }

    protected function replaceConfigKey($key, $value, $backup = true, $file = null): void
    {
        if (! $file) {
            $configPath = config_path($this->configFile);
        } else {
            $configPath = $file;
        }

        $configData = include $configPath;

        $keys = explode('.', $key);

        $configPart = &$configData;

        foreach ($keys as $i => $part) {
            if ($i === count($keys) - 1) {
                $configPart[$part] = $value;
            } else {
                if (! isset($configPart[$part])) {
                    $configPart[$part] = [];
                }
                $configPart = &$configPart[$part];
            }
        }
        if ($backup) {
            if (file_exists($configPath.'.bak')) {
                unlink($configPath.'.bak');
            }
            copy($configPath, $configPath.'.bak');
        }
        file_put_contents($configPath, '<?php return '.$this->var_export_short($configData, true).';');
        system(base_path('./vendor/bin/pint').' '.$configPath);
        config()->offsetUnset(basename($this->configFile, '.php'));
    }

    public function var_export_short($expression, $return = false): ?string
    {
        $export = var_export($expression, true);
        $export = preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = implode(PHP_EOL, array_filter(['['] + $array));

        if ($return) {
            return $export;
        } else {
            echo $export;
        }

        return null;
    }

    public function getAWSRepoID(string $ecrRepository): string
    {
        $ecrRepositoryParts = explode('/', $ecrRepository);

        return $ecrRepositoryParts[count($ecrRepositoryParts) - 1];
    }

    public function generateSubnets($baseNetwork, $mask, $newMask): array
    {
        $parts = explode('.', $baseNetwork);
        $baseNetworkLong = ip2long("$parts[0].$parts[1].0.0");
        $numSubnets = pow(2, $newMask - $mask);

        $subnets = [];
        for ($i = 0; $i < $numSubnets; $i++) {
            $subnet = long2ip($baseNetworkLong + ($i * pow(2, (32 - $newMask))));
            $subnets[] = "$subnet/$newMask";
        }

        return $subnets;
    }

    public function waitUntilTrue(Closure $condition, int $timeout = 360, int $interval = 10): bool
    {
        $startTime = time();

        while (time() - $startTime < $timeout) {
            if (call_user_func($condition)) {
                return true;
            }

            sleep($interval);
        }

        return false;
    }

    public function getDevtoolsVersion()
    {
        return config('devtools.version', '0.0.1');
    }
}
