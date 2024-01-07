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
    protected $signature = 'docker:compose-up';

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
        $this->runDockerCmd(['compose', 'up']);
    }
}
