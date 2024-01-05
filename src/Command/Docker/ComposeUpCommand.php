<?php

namespace Uxmal\Devtools\Command\Docker;

use Docker\Docker;
use Illuminate\Console\Command;
use Uxmal\Devtools\Traits\GeneralUtils;

class ComposeUpCommand extends Command
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
    protected $signature = 'docker:compose-up';

    public function __construct()
    {
        parent::__construct();
        $this->configureSilentOption();
    }

    public function handle(): void
    {
        if ($this->checkEnvironment() === false) {
            $this->error('Environment not ready.');
            exit(1);
        }

        $this->upDockerCompose();
        /*
        $docker = Docker::create();
        $containers = $docker->imageList();



        foreach ($containers as $container) {
            var_dump($container->getRepoTags());
        }
        */

    }

    public function upDockerCompose(): void
    {
        $this->devMode = true;
        $this->runDockerCmd(['compose', 'up']);
    }
}
