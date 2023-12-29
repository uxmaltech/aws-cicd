<?php

namespace Uxmal\AwsCICD\Command\Docker;

use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\AwsCICD\Traits\ProcessUtils;
use MCStreetguy\ComposerParser\Factory as ComposerParser;

class ComposeBuildCommand extends Command
{
    use ProcessUtils;

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
     *
     */
    protected $name = 'docker:compose-build';

    public function handle(): void
    {
        try {
            if ($this->checkEnvironment() === false) {
                exit(1);
            }
        } catch (ProcessFailedException $exception) {
            $this->warn("An error occurred: " . $exception->getMessage());
        }


        $this->composeBuild();
    }

    private function composeBuild(): void
    {
        $laravelAppName = config('aws-cicd.laravel.app.name', 'laravel-app');
        $debug = config('aws-cicd.laravel.app.debug', false);

        $this->info("Dockeriz-ing Laravel Application ($laravelAppName)...");

        $dockerImageDir = $this->laravel->basePath('docker-images');
        $buildDir = $dockerImageDir . '/build';

        if (is_dir($buildDir)) {
            $this->info('Removing old build directory...');
            $this->runProcess("rm -rf $buildDir");
        }

        $appBuildDir = $buildDir . '/' . $laravelAppName;

        if (!is_dir($appBuildDir) && mkdir($appBuildDir, 0777, true)) {
            $this->info('Build directory created.');
        } else if (!is_dir($appBuildDir)) {
            $this->error('Build directory could not be created.');
            exit(1);
        }

        // Check if have a protected packages from uxmal.
        $this->checkProtectedPackages();

        if (!$this->isCommandAvailable('composer')) {
            $this->error('Composer is not available. Please install Composer first.');
            exit(1);
        }

        $this->info('Copying files to build directory...');
        try {
            $this->runCmd(['bash', '-c', "git archive $this->gitCommit | tar -x -C $appBuildDir"]);
        } catch (ProcessFailedException $exception) {
            $this->warn("An error occurred: " . $exception->getMessage());
        }

        $this->info('Running composer install on build directory...');
        if(!empty($this->pathRepositoriesToCopy)){
            foreach ($this->pathRepositoriesToCopy as $pathRepositoryToCopy) {
                if( $debug ) {
                    $this->info("Copying $pathRepositoryToCopy to $appBuildDir/$pathRepositoryToCopy");
                }
                $this->runCmd(['bash', '-c', "cp -r $pathRepositoryToCopy $appBuildDir/$pathRepositoryToCopy"]);
            }
        }

        $cmd = "sed -i 's/\"symlink\": true/\"symlink\": false/g' $appBuildDir/composer.json";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd]);

        if( $debug ){
            $this->info("Running Comand [bash -c 'cd $appBuildDir && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --prefer-dist --no-cache']");
        }
        try {
            $this->runCmd(['bash', '-c', "cd $appBuildDir && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts --prefer-dist --no-cache"], ['COMPOSER_MIRROR_PATH_REPOS' => '1']);
        } catch (ProcessFailedException $exception) {
            $this->warn("An error occurred: " . $exception->getMessage());
        }

        if( $debug ){
            $this->info("Running Comand [bash -c 'cd $appBuildDir && npm run build']");
        }
        try {
            $this->runCmd(['bash', '-c', "cd $appBuildDir && npm run build"]);
        } catch (ProcessFailedException $exception) {
            $this->warn("An error occurred: " . $exception->getMessage());
        }


        $envsubstVars = [
            'APP_TAG' => $this->laravelAppTag,
            'APP_NAME' => $this->laravelAppName,
            'APP_KEY' => $this->laravelAppKey,
            'APP_DEBUG' => $this->laravelAppDebug ? 'true' : 'false',
            'APP_ENV' => $this->laravelAppEnv,
            'APP_AUTHOR' => $this->laravelAppAuthor,
            'APP_EMAIL' => $this->laravelAppEmail,
            'APP_URL' => $this->laravelAppUrl,
            'TIMEZONE' => $this->laravelAppTimeZone,
            'NGINX_HOST' => $this->awsEcsServiceNginxClusterName,
            'NGINX_PORT' => $this->awsEcsServiceNginxClusterPort,
            'NGINX_ALPINE_VERSION' => $this->nginxAlpineVersion,
            'NGINX_VERSION' => $this->nginxVersion,
            'PHP_FPM_HOST' => $this->awsEcsServicePhpFpmClusterName,
            'PHP_FPM_PORT'=> $this->awsEcsServicePhpFpmClusterPort,
            'PHP_FPM_VERSION' => $this->phpFpmVersion,
            'PHP_FPM_ALPINE_VERSION' => $this->phpFpmAlpineVersion,
            'DOLLAR' => '$',
            'AWS_ECS_SERVICE_PORT' => $this->awsEcsServicePort,
            'AWS_ECR_REPOSITORY_USERNAME' => $this->awsEcrRepositoryUsername
        ];

        $dockerConfDir = $buildDir.'/docker/conf';
        if (!is_dir($dockerConfDir) && mkdir($dockerConfDir, 0777, true)) {
            $this->info('Build directory '.$dockerConfDir.' created.');
        } else if (!is_dir($dockerConfDir)) {
            $this->error('Build directory '.$dockerConfDir.' could not be created.');
            exit(1);
        }

        $dockerNginxDir = $buildDir.'/docker/nginx';
        if (!is_dir($dockerNginxDir) && mkdir($dockerNginxDir, 0777, true)) {
            $this->info('Build directory '.$dockerNginxDir.' created.');
        } else if (!is_dir($dockerNginxDir)) {
            $this->error('Build directory '.$dockerNginxDir.' could not be created.');
            exit(1);
        }

        $dockerPHPFPMDir = $buildDir.'/docker/php-fpm';
        if (!is_dir($dockerPHPFPMDir) && mkdir($dockerPHPFPMDir, 0777, true)) {
            $this->info('Build directory '.$dockerPHPFPMDir.' created.');
        } else if (!is_dir($dockerPHPFPMDir)) {
            $this->error('Build directory '.$dockerPHPFPMDir.' could not be created.');
            exit(1);
        }



        $cmd = "envsubst < $dockerImageDir/compose/nginx/nginx.conf > $dockerConfDir/nginx.conf";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/compose/nginx/dockerfile-tmpl > $dockerNginxDir/Dockerfile";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/compose/php-fpm/dockerfile-tmpl > $dockerPHPFPMDir/Dockerfile";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        //envsubst < "$THIS_DIR"/resources/docker/docker-compose-tmpl.yml > "$THIS_DIR"/docker-compose.yml

        $cmd = "envsubst < $dockerImageDir/compose/docker-compose-tmpl.yml > {$this->laravel->basePath("docker-compose.yml")}";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        //envsubst < "$THIS_DIR"/resources/docker/tmpl.env > "$THIS_DIR"/docker/backoffice-ui-site/.env

        $cmd = "envsubst < $dockerImageDir/compose/tmpl.env > $appBuildDir/.env";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "docker compose build";
        if( $debug ){
            $this->info("Running Command [bash -c '$cmd']");
        }
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

    }

}
