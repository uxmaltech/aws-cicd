<?php

namespace Uxmal\Devtools\Command\Docker;

use Illuminate\Console\Command;
use Random\RandomException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildAppImagesCommand extends Command
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
    protected $signature = 'docker:build-app-images 
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
            $this->warn('An error occurred: ' . $exception->getMessage());
        } catch (RandomException $e) {
            $this->warn('An error occurred: ' . $e->getMessage());
        }

        system('clear');
        $this->composeBuild();
    }

    private function composeBuild(): void
    {

        $ECR_PHP_FPM_LATEST_BASE_IMAGE = config('aws-ecr.php-fpm.latest-base-image');
        $ECR_PHP_FPM_LATEST_BASE_IMAGE_FROM = $ECR_PHP_FPM_LATEST_BASE_IMAGE;
        if (strpos($ECR_PHP_FPM_LATEST_BASE_IMAGE, '/')) {
            $ECR_PHP_FPM_LATEST_BASE_IMAGE_FROM = explode('/', $ECR_PHP_FPM_LATEST_BASE_IMAGE)[1];
        }
        $ECR_PHP_FPM_EXPOSED_PORT = config('aws-ecr.php-fpm.exposed-port', '9000/tcp');
        $ECR_PHP_FPM_PORT = explode('/', $ECR_PHP_FPM_EXPOSED_PORT)[0];
        $ECR_PHP_FPM_PROTOCOL = explode('/', $ECR_PHP_FPM_EXPOSED_PORT)[1];
        $ECR_PHP_FPM_SERVICE_DN = config('aws-ecr.php-fpm.app-image') . '.' . config('uxmaltech.internal_domain');


        $ECR_NGINX_LATEST_BASE_IMAGE = config('aws-ecr.nginx.latest-base-image');
        $ECR_NGINX_LATEST_BASE_IMAGE_FROM = $ECR_NGINX_LATEST_BASE_IMAGE;
        if (strpos($ECR_NGINX_LATEST_BASE_IMAGE, '/')) {
            $ECR_NGINX_LATEST_BASE_IMAGE_FROM = explode('/', $ECR_NGINX_LATEST_BASE_IMAGE)[1];
        }
        $ECR_NGINX_EXPOSED_PORT = config('aws-ecr.nginx.exposed-port', '80/tcp');
        $ECR_NGINX_PORT = explode('/', $ECR_NGINX_EXPOSED_PORT)[0];
        $ECR_NGINX_PROTOCOL = explode('/', $ECR_NGINX_EXPOSED_PORT)[1];
        $ECR_NGINX_SERVICE_DN = config('aws-ecr.nginx.app-image') . '.' . config('uxmaltech.internal_domain');
        $ECR_TIMEZONE = config('aws-ecr.timezone', 'America/Mexico_City');

        $UXMALTECH_APP_NAME = config('uxmaltech.name', config('APP_NAME', 'laravel'));
        $UXMALTECH_CURRENT_TAG = $this->ecrImageTag;
        $UXMALTECH_AUTHOR_NAME = config('uxmaltech.author.name', config('APP_AUTHOR', 'UxmalTech'));
        $UXMALTECH_AUTHOR_EMAIL = config('uxmaltech.author.email', config('APP_AUTHOR_EMAIL', 'name@email.com'));

        $ECR_NGINX_APP_IMAGE_TAG = $UXMALTECH_APP_NAME . '-nginx:' . $UXMALTECH_CURRENT_TAG;
        $ECR_PHP_FPM_APP_IMAGE_TAG = $UXMALTECH_APP_NAME . '-php-fpm:' . $UXMALTECH_CURRENT_TAG;

        $envsubstVars = [
            'DOLLAR' => '$',
            'UXMALTECH_CURRENT_TAG' => $UXMALTECH_CURRENT_TAG,
            'UXMALTECH_AUTHOR_NAME' => $UXMALTECH_AUTHOR_NAME,
            'UXMALTECH_AUTHOR_EMAIL' => $UXMALTECH_AUTHOR_EMAIL,
            'UXMALTECH_APP_NAME' => $UXMALTECH_APP_NAME,


            'ECR_TIMEZONE' => $ECR_TIMEZONE,
            'ECR_PHP_FPM_LATEST_BASE_IMAGE' => $ECR_PHP_FPM_LATEST_BASE_IMAGE,
            'ECR_PHP_FPM_LATEST_BASE_IMAGE_FROM' => $ECR_PHP_FPM_LATEST_BASE_IMAGE_FROM,
            'ECR_PHP_FPM_APP_IMAGE_TAG' => $ECR_PHP_FPM_APP_IMAGE_TAG,
            'ECR_PHP_FPM_PORT' => $ECR_PHP_FPM_PORT,
            'ECR_PHP_FPM_PROTOCOL' => $ECR_PHP_FPM_PROTOCOL,
            'ECR_PHP_FPM_SERVICE_DN' => $ECR_PHP_FPM_SERVICE_DN,

            'ECR_NGINX_LATEST_BASE_IMAGE' => $ECR_NGINX_LATEST_BASE_IMAGE,
            'ECR_NGINX_LATEST_BASE_IMAGE_FROM' => $ECR_NGINX_LATEST_BASE_IMAGE_FROM,
            'ECR_NGINX_APP_IMAGE_TAG' => $ECR_NGINX_APP_IMAGE_TAG,
            'ECR_NGINX_PORT' => $ECR_NGINX_PORT,
            'ECR_NGINX_PROTOCOL' => $ECR_NGINX_PROTOCOL,
            'ECR_NGINX_SERVICE_DN' => $ECR_NGINX_SERVICE_DN,
        ];

        $headers = ['Variable', 'Contenido'];
        // Mostrar la tabla
        $envTable = [];
        foreach ($envsubstVars as $variable => $contenido) {
            $envTable[] = [$variable, $contenido];
        }
        $this->table($headers, $envTable);

        if ($this->confirm('Do you wish to continue?') === false) {
            exit(1);
        }

        $this->line('(Dockerizing) Laravel Application' . "\t\t" . '[<comment>' . $UXMALTECH_APP_NAME . '</comment>]');
        $this->line('Build directory                  ' . "\t\t" . '[<comment>' . $this->laravel->basePath('docker-images/build') . '</comment>]');
        $dockerImageDir = $this->laravel->basePath('docker-images');
        $buildDir = $dockerImageDir . '/build';


        if (is_dir($buildDir)) {
            $this->runProcess("rm -rf $buildDir");
            $this->line('Removing old build directory     ' . "\t\t" . '[<comment>OK</comment>]');
        }

        $appBuildDir = $buildDir . '/' . $UXMALTECH_APP_NAME;

        if (!is_dir($appBuildDir) && mkdir($appBuildDir, 0777, true)) {
            $this->line('Build directory created         ' . "\t\t" . '[<comment>OK</comment>]');
        } elseif (!is_dir($appBuildDir)) {
            $this->error('Build directory could not be created.');
            exit(1);
        }

        $this->output->write('Copying files to build directory...' . "\t\t");
        if (!is_dir($this->laravel->basePath('.git'))) { // Si no existe el directorio .git, se copian los archivos con rsync
            $cmd = sprintf('rsync -av --exclude=%s %s %s', escapeshellarg('docker-images'), escapeshellarg($this->laravel->basePath() . '/'), escapeshellarg($appBuildDir . '/'));
        } elseif ($this->devMode) { // Si existe el directorio .git y estamos en devMode, se copian los archivos con git ls-files
            $cmd = "git ls-files -z | tar --null -T - -cz | tar -x -C $appBuildDir";
        } else { // Si existe el directorio .git y NO estamos en devMode, se copian los archivos con git archive (current branch)
            $cmd = "git archive $this->gitCommit | tar -x -C $appBuildDir";
        }
        $this->runCmd(['bash', '-c', $cmd]);
        $this->line('[<comment>OK</comment>]');

        $this->checkProtectedPackages();

        if (!empty($this->pathRepositoriesToCopy)) {
            foreach ($this->pathRepositoriesToCopy as $pathRepositoryToCopy) {
                $this->line("Copying $pathRepositoryToCopy to {BuildDir}/$pathRepositoryToCopy\t\t" . '[<comment>OK</comment>]');
                $this->runCmd(['bash', '-c', "cp -r $pathRepositoryToCopy $appBuildDir/$pathRepositoryToCopy"]);
            }
        }

        $composer_command = 'install';
        if ($this->devMode) {
            $cmd = "sed -i'' -e 's/\"symlink\": true/\"symlink\": false/g' $appBuildDir/composer.json";
            $this->runCmd(['bash', '-c', $cmd]);
            $composer_command = 'update';
        } else {
            if (str_contains('"symlink": true', file_get_contents($appBuildDir . '/composer.json'))) {
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

        $this->output->write("Running composer $composer_command in build directory...\t\t");
        try {
            $this->runCmd(['bash', '-c', "cd $appBuildDir && npm run build"]);
        } catch (ProcessFailedException $exception) {
            $this->error('An error occurred: ' . $exception->getMessage());
            exit(1);
        }

        $this->line('[<comment>OK</comment>]');

        $this->output->write("Build docker/{php-fpm,nginx} work directory...\t\t");
        $dockerConfDir = $buildDir . '/docker/conf';
        if (!is_dir($dockerConfDir) && !mkdir($dockerConfDir, 0777, true)) {
            $this->error('Build directory ' . $dockerConfDir . ' could not be created.');
            exit(1);
        }

        $dockerNginxDir = $buildDir . '/docker/nginx';
        if (!is_dir($dockerNginxDir) && !mkdir($dockerNginxDir, 0777, true)) {
            $this->error('Build directory ' . $dockerNginxDir . ' could not be created.');
            exit(1);
        }

        $dockerPHPFPMDir = $buildDir . '/docker/php-fpm';
        if (!is_dir($dockerPHPFPMDir) && !mkdir($dockerPHPFPMDir, 0777, true)) {
            $this->error('Build directory ' . $dockerPHPFPMDir . ' could not be created.');
            exit(1);
        }
        $this->line('[<comment>OK</comment>]');

        $cmd = "envsubst < $dockerImageDir/app-images/nginx/nginx.conf > $dockerConfDir/nginx.conf";
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/app-images/nginx/dockerfile-tmpl > $dockerNginxDir/Dockerfile";
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/app-images/php-fpm/dockerfile-tmpl > $dockerPHPFPMDir/Dockerfile";
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        //envsubst < "$THIS_DIR"/resources/docker/docker-compose-tmpl.yml > "$THIS_DIR"/docker-compose.yml

        $cmd = "envsubst < $dockerImageDir/app-images/docker-compose-tmpl.yml > {$this->laravel->basePath('docker-compose.yml')}";
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        $cmd = "envsubst < $dockerImageDir/app-images/tmpl.env > $appBuildDir/.env";
        $this->runCmd(['bash', '-c', $cmd], $envsubstVars);

        //docker build -f docker-images/build/docker/php-fpm/Dockerfile .
        $this->runDockerCmd(['build', '-f', $dockerPHPFPMDir . '/Dockerfile', '.', '-t', $ECR_PHP_FPM_APP_IMAGE_TAG]);

        $this->runDockerCmd(['build', '-f', $dockerNginxDir . '/Dockerfile', '.', '-t', $ECR_NGINX_APP_IMAGE_TAG]);

        /*
        if (!$this->devMode) {
            // En modo producción se tagea con latest para despues hacer el push de las imágenes a ECR.
            // y que los servicios ejecuten las imágenes con el tag latest.
            $this->runDockerCmd(['tag', $this->clusterContainerNginxAppRepositoryTag, $this->clusterContainerNginxAppRepositoryTagLatest]);
            $this->runDockerCmd(['tag', $this->clusterContainerPhpFpmAppRepositoryTag, $this->clusterContainerPhpFpmAppRepositoryTagLatest]);
        }
        */

        if (!$this->devMode) {
            $this->info('Production Mode Removing build directory...');
            $this->runProcess("rm -rf $buildDir");
        }

    }
}
