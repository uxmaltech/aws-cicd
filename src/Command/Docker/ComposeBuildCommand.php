<?php

namespace Uxmal\Devtools\Command\Docker;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Traits\GeneralUtils;

class ComposeBuildCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     *
     * --build
     * --run
     * --check
     * ? --push
     * ? --pull
     * ? --tag=latest
     * ? --tag=dev
     * ? --tag=prod
     * ? --tag=staging
     * ? --tag=testing
     * ? --tag=qa
     * ? --push-ecr=tag
     */
    protected $signature = 'docker:compose-build 
                        {--no-delete}';

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

        $this->composeBuild();
    }

    private function composeBuild(): void
    {

        $envsubstVars = [
            'APP_TAG' => $this->clusterImageTag,
            'APP_NAME' => $this->laravelAppName,
            'APP_KEY' => $this->laravelAppKey,
            'APP_DEBUG' => $this->laravelAppDebug ? 'true' : 'false',
            'APP_ENV' => $this->laravelAppEnv,
            'APP_AUTHOR' => $this->laravelAppAuthor,
            'APP_EMAIL' => $this->laravelAppEmail,
            'APP_URL' => $this->laravelAppUrl,
            'TIMEZONE' => $this->laravelAppTimeZone,
            'NGINX_ALPINE_VERSION' => $this->clusterContainerNginxAlpineVersion,
            'NGINX_VERSION' => $this->clusterContainerNginxVersion,

            'PHP_FPM_VERSION' => $this->clusterContainerPhpFpmVersion,
            'PHP_FPM_ALPINE_VERSION' => $this->clusterContainerPhpFpmAlpineVersion,
            'DOLLAR' => '$',
            'AWS_ECS_SERVICE_PORT' => $this->awsEcsServicePort,

            'NGINX_BASE_REPOSITORY_TAG' => $this->clusterContainerNginxRepositoryTag,

            'PHP_FPM_BASE_REPOSITORY_TAG' => $this->clusterContainerPhpFpmRepositoryTag,

        ];

        $newEnvsubstVars = [
            '======================' => '======================',
            'CLUSTER_PORT' => $this->clusterPort,

            'PHP_FPM_SERVICE_PORT' => $this->clusterContainerPhpFpmPort,
            'PHP_FPM_SERVICE_HOST' => $this->clusterPhpFpmServiceHost,
            'PHP_FPM_APP_REPOSITORY' => $this->clusterContainerPhpFpmAppRepository,
            'PHP_FPM_APP_REPOSITORY_TAG' => $this->clusterContainerPhpFpmAppRepositoryTag,
            'PHP_FPM_APP_REPOSITORY_TAG_LATEST' => $this->clusterContainerPhpFpmAppRepositoryTagLatest ?? 'PENDIENTE',

            'NGINX_SERVICE_PORT' => $this->clusterContainerNginxPort,
            'NGINX_SERVICE_HOST' => $this->clusterNginxServiceHost,
            'NGINX_APP_REPOSITORY' => $this->clusterContainerNginxAppRepository,
            'NGINX_APP_REPOSITORY_TAG' => $this->clusterContainerNginxAppRepositoryTag,
            'NGINX_APP_REPOSITORY_TAG_LATEST' => $this->clusterContainerNginxAppRepositoryTagLatest,

        ];

        $envsubstVars += $newEnvsubstVars;

        $headers = ['Variable', 'Contenido'];
        // Mostrar la tabla
        $envTable = [];
        foreach ($envsubstVars as $variable => $contenido) {
            $envTable[] = [$variable, $contenido];
        }
        $this->table($headers, $envTable);

        $debug = config('aws-cicd.laravel.app.debug', false);
        $devMode = config('aws-cicd.uxmaltech.devMode', false);

        $this->info("Dockeriz-ing Laravel Application ($this->laravelAppName)...");

        $dockerImageDir = $this->laravel->basePath('docker-images');
        $buildDir = $dockerImageDir.'/build';

        if (is_dir($buildDir)) {
            $this->info('Removing old build directory...');
            $this->runProcess("rm -rf $buildDir");
        }

        $appBuildDir = $buildDir.'/'.$this->laravelAppName;

        if (! is_dir($appBuildDir) && mkdir($appBuildDir, 0777, true)) {
            $this->info('Build directory created.');
        } elseif (! is_dir($appBuildDir)) {
            $this->error('Build directory could not be created.');
            exit(1);
        }

        // Check if have a protected packages from uxmal.
        $this->checkProtectedPackages();

        if (! $this->isCommandAvailable('composer')) {
            $this->error('Composer is not available. Please install Composer first.');
            exit(1);
        }

        $this->warn('Copying files to build directory...');
        if (! is_dir($this->laravel->basePath('.git'))) {
            $cmd = sprintf('rsync -av --exclude=%s %s %s', escapeshellarg('docker-images'), escapeshellarg($this->laravel->basePath().'/'), escapeshellarg($appBuildDir.'/'));
            if ($debug) {
                $this->info("Running Command [bash -c '$cmd']");
            }
            $this->runCmd(['bash', '-c', $cmd]);
        } elseif ($devMode) {
            $cmd = "git ls-files -z | tar --null -T - -cz | tar -x -C $appBuildDir";
            if ($debug) {
                $this->info("Running Command [bash -c '$cmd']");
            }
            $this->runCmd(['bash', '-c', $cmd]);
        } else {
            $cmd = "git archive $this->gitCommit | tar -x -C $appBuildDir";
            if ($debug) {
                $this->info("Running Command [bash -c '$cmd']");
            }
            $this->runCmd(['bash', '-c', $cmd]);
        }

        $this->info('Running composer install on build directory...');
        if (! empty($this->pathRepositoriesToCopy)) {
            foreach ($this->pathRepositoriesToCopy as $pathRepositoryToCopy) {
                if ($debug) {
                    $this->info("Copying $pathRepositoryToCopy to $appBuildDir/$pathRepositoryToCopy");
                }
                $this->runCmd(['bash', '-c', "cp -r $pathRepositoryToCopy $appBuildDir/$pathRepositoryToCopy"]);
            }
        }

        $composer_command = 'install';

        if ($devMode) {
            $cmd = "sed -i'' -e 's/\"symlink\": true/\"symlink\": false/g' $appBuildDir/composer.json";
            if ($debug) {
                $this->info("Running Command [bash -c '$cmd']");
            }
            $this->runCmd(['bash', '-c', $cmd]);
            $composer_command = 'update';
        } else {
            if (str_contains('"symlink": true', file_get_contents($appBuildDir.'/composer.json'))) {
                $this->error('Symlink is not allowed in production mode. Please remove "symlink": true from composer.json file.');
                exit(1);
            }
        }

        $this->runComposerCmd([
            $composer_command,
            '--optimize-autoloader',
            '--no-interaction',
            '--no-progress',
            '--no-scripts',
            '--prefer-dist',
            '--no-cache',
            '--no-dev',
        ], $appBuildDir);

        if ($debug) {
            $this->info("Running Command [bash -c 'cd $appBuildDir && npm run build']");
        }
        try {
            $this->runCmd(['bash', '-c', "cd $appBuildDir && npm run build"]);
        } catch (ProcessFailedException $exception) {
            $this->warn('An error occurred: '.$exception->getMessage());
        }

        /*
         $this->clusterContainerPhpFpmRepository = "$this->phpFpmUsername/php$this->clusterContainerPhpFpmVersion-fpm-alpine$this->clusterContainerPhpFpmAlpineVersion";
        $this->clusterContainerPhpFpmRepositoryTag = "$this->clusterContainerPhpFpmRepository:$this->clusterContainerPhpFpmTag";
        $this->clusterContainerNginxRepository = "$this->nginxUsername/nginx-alpine$this->clusterContainerNginxAlpineVersion";
        $this->clusterContainerNginxRepositoryTag = "$this->clusterContainerNginxRepository:$this->clusterContainerNginxTag";

        $this->clusterContainerPhpFpmAppRepository = "$this->phpFpmUsername/php-fpm-$this->laravelAppName";
        $this->clusterContainerPhpFpmAppRepositoryTag = "$this->clusterContainerPhpFpmAppRepository:$this->clusterImageTag";
        $this->nginxAppRepository = "$this->nginxUsername/nginx-$this->laravelAppName";
        $this->nginxAppRepositoryTag = "$this->nginxAppRepository:$this->clusterImageTag";
         */

        $dockerConfDir = $buildDir.'/docker/conf';
        if (! is_dir($dockerConfDir) && mkdir($dockerConfDir, 0777, true)) {
            $this->info('Build directory '.$dockerConfDir.' created.');
        } elseif (! is_dir($dockerConfDir)) {
            $this->error('Build directory '.$dockerConfDir.' could not be created.');
            exit(1);
        }

        $dockerNginxDir = $buildDir.'/docker/nginx';
        if (! is_dir($dockerNginxDir) && mkdir($dockerNginxDir, 0777, true)) {
            $this->info('Build directory '.$dockerNginxDir.' created.');
        } elseif (! is_dir($dockerNginxDir)) {
            $this->error('Build directory '.$dockerNginxDir.' could not be created.');
            exit(1);
        }

        $dockerPHPFPMDir = $buildDir.'/docker/php-fpm';
        if (! is_dir($dockerPHPFPMDir) && mkdir($dockerPHPFPMDir, 0777, true)) {
            $this->info('Build directory '.$dockerPHPFPMDir.' created.');
        } elseif (! is_dir($dockerPHPFPMDir)) {
            $this->error('Build directory '.$dockerPHPFPMDir.' could not be created.');
            exit(1);
        }

        $cmd = "envsubst < $dockerImageDir/compose/nginx/nginx.conf > $dockerConfDir/nginx.conf";
        if ($debug) {
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/compose/nginx/dockerfile-tmpl > $dockerNginxDir/Dockerfile";
        if ($debug) {
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/compose/php-fpm/dockerfile-tmpl > $dockerPHPFPMDir/Dockerfile";
        if ($debug) {
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        //envsubst < "$THIS_DIR"/resources/docker/docker-compose-tmpl.yml > "$THIS_DIR"/docker-compose.yml

        $cmd = "envsubst < $dockerImageDir/compose/docker-compose-tmpl.yml > {$this->laravel->basePath('docker-compose.yml')}";
        if ($debug) {
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/compose/tmpl.env > $appBuildDir/.env";
        if ($debug) {
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $this->runDockerCmd(['compose', 'build']);
        if (! $this->devMode) {
            // En modo producción se tagea con latest para despues hacer el push de las imágenes a ECR.
            // y que los servicios ejecuten las imágenes con el tag latest.
            $this->runDockerCmd(['tag', $this->clusterContainerNginxAppRepositoryTag, $this->clusterContainerNginxAppRepositoryTagLatest]);
            $this->runDockerCmd(['tag', $this->clusterContainerPhpFpmAppRepositoryTag, $this->clusterContainerPhpFpmAppRepositoryTagLatest]);
        }

        if (! $this->option('no-delete')) {
            $this->info('Removing build directory...');
            $this->runProcess("rm -rf $buildDir");
        }

    }
}
