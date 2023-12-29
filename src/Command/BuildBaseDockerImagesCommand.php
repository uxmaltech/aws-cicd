<?php

namespace Uxmal\AwsCICD\Command;

use Illuminate\Console\Command;
use JetBrains\PhpStorm\NoReturn;
use Uxmal\AwsCICD\Traits\ProcessUtils;

class BuildBaseDockerImagesCommand extends Command
{
    use ProcessUtils;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'aws-cicd:build-base-images';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build the base docker image for CI/CD.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->buildDockerImage();
    }

    /**
     * Build the base docker image.
     *
     * @return void
     */
    #[NoReturn]
    protected function buildDockerImage(): void
    {
        if( $this->checkEnvironment() === false ) {
            exit(1);
        }

        $this->info('Building base docker images...');

        /**
         * php-fpm
         */
        $cwd = $this->laravel->basePath("docker-images/php$this->phpVersion-fpm");
        $dockerPHPFPMImage = "$this->imagesUsername/php$this->phpVersion-fpm-alpine$this->alpineVersion:$this->imagesTag";
        $this->runProcess("docker build -t $dockerPHPFPMImage .", $cwd);
        $this->info("Docker image built $dockerPHPFPMImage successfully.");

        /**
         * nginx
         */
        $cwd = $this->laravel->basePath("docker-images/nginx");
        $dockerNginxImage = "$this->imagesUsername/nginx-alpine$this->alpineVersion:$this->imagesTag";
        $this->runProcess("docker build -t $dockerNginxImage .", $cwd);
        $this->info("Docker image built $dockerNginxImage successfully.");

        exit(0);
    }
}
