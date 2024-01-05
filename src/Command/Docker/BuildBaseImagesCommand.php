<?php

namespace Uxmal\Devtools\Command\Docker;

use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Uxmal\Devtools\Traits\GeneralUtils;

class BuildBaseImagesCommand extends Command
{
    use GeneralUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'docker:build-base-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build the base docker image for CI/CD.';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            exit(1);
        }
        $this->buildDockerImage();
    }

    /**
     * Build the base docker image.
     */
    #[NoReturn]
    protected function buildDockerImage(): void
    {
        $headers = ['Variable', 'Contenido'];
        // Mostrar la tabla
        $envTable = [
            ['PHP_FPM_BASE_REPOSITORY', $this->clusterContainerPhpFpmRepository],
            ['PHP_FPM_BASE_IMAGE', $this->clusterContainerPhpFpmBaseImage],
            ['PHP_FPM_BASE_TAG', $this->clusterContainerPhpFpmTag],
            ['NGINX_BASE_REPOSITORY', $this->clusterContainerNginxRepository],
            ['NGINX_BASE_IMAGE', $this->clusterContainerNginxBaseImage],
            ['NGINX_BASE_TAG', $this->clusterContainerNginxTag],
        ];

        $this->table($headers, $envTable);

        /**
         * php-fpm
         */
        $cwd = $this->laravel->basePath("docker-images/php$this->clusterContainerPhpFpmVersion-fpm");
        $dockerPHPFPMImage = "$this->clusterContainerPhpFpmRepository:$this->clusterContainerPhpFpmTag";
        $this->runDockerCmd(['build',  '-t', $dockerPHPFPMImage, '.'], $cwd);
        $this->info("Docker image built $dockerPHPFPMImage successfully.");

        /**
         * nginx
         */
        $cwd = $this->laravel->basePath('docker-images/nginx');
        $dockerNginxImage = "$this->clusterContainerNginxRepository:$this->clusterContainerNginxTag";
        $this->runDockerCmd(['build',  '-t', $dockerNginxImage, '.'], $cwd);
        $this->info("Docker image built $dockerNginxImage successfully.");

        exit(0);
    }
}
