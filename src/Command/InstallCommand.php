<?php

namespace Uxmal\AwsCICD\Command;


use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'aws-cicd:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the package.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->install();
    }

    /**
     * Install the package.
     *
     * @return void
     */
    protected function install()
    {
        $this->info('Installing package...');
        $this->call('vendor:publish', [
            '--provider' => 'Uxmal\AwsCICD\AwsCICDServiceProvider',
            '--tag' => 'aws-cicd-config'
        ]);
        $this->call('vendor:publish', [
            '--provider' => 'Uxmal\AwsCICD\AwsCICDServiceProvider',
            '--tag' => 'aws-cicd-docker',
            '--force' => true,
        ]);

    }
}
