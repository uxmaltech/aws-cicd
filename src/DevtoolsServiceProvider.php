<?php

namespace Uxmal\Devtools;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Uxmal\Devtools\Command\Aws\DeployInfrastructureCommand;
use Uxmal\Devtools\Command\Aws\ECRDockerLoginCommand;
use Uxmal\Devtools\Command\Aws\ECRListRepositoriesCommand;
use Uxmal\Devtools\Command\Aws\ECRPushToRepositoryCommand;
use Uxmal\Devtools\Command\BuildInfrastructureConfigCommand;
use Uxmal\Devtools\Command\Docker\BuildAppImagesCommand;
use Uxmal\Devtools\Command\Docker\BuildBaseImagesCommand;
use Uxmal\Devtools\Command\Docker\ComposeUpCommand;
use Uxmal\Devtools\Command\GitHub\CommitPushCommand;
use Uxmal\Devtools\Command\GitHub\CreateBranchCommand;
use Uxmal\Devtools\Command\GitHub\CreatePullRequestCommand;
use Uxmal\Devtools\Command\GitHub\ResetTokenCommand;
use Uxmal\Devtools\Command\InstallCommand;
use Uxmal\Devtools\Command\TestCommand;
use Uxmal\Devtools\Command\Uxmaltech\AddBackOfficeUICommand;
use Uxmal\Devtools\Command\Uxmaltech\BuildAwsEcrConfigCommand;
use Uxmal\Devtools\Command\Uxmaltech\BuildAwsEcsConfigCommand;
use Uxmal\Devtools\Command\Uxmaltech\BuildAwsVpcConfigCommand;
use Uxmal\Devtools\Command\Uxmaltech\BuildDockerizedConfigCommand;
use Uxmal\Devtools\Command\Uxmaltech\BuildUxmalTechConfigCommand;

class DevtoolsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->configurePublishing();
    }

    /**
     * Register the console commands for the package.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Docker
                BuildBaseImagesCommand::class,
                BuildAppImagesCommand::class,
                InstallCommand::class,
                BuildUxmalTechConfigCommand::class,
                ComposeUpCommand::class,
                // Devtools
                AddBackOfficeUICommand::class,
                BuildInfrastructureConfigCommand::class,
                BuildUxmalTechConfigCommand::class,
                BuildAwsEcrConfigCommand::class,
                BuildAwsVpcConfigCommand::class,
                BuildAwsEcsConfigCommand::class,
                BuildDockerizedConfigCommand::class,
                TestCommand::class,
                // AWS
                ECRPushToRepositoryCommand::class,
                ECRListRepositoriesCommand::class,
                ECRDockerLoginCommand::class,
                DeployInfrastructureCommand::class,
                // GitHub
                CreateBranchCommand::class,
                CommitPushCommand::class,
                CreatePullRequestCommand::class,
                ResetTokenCommand::class,
            ]);
        }
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../docker-images' => $this->app->basePath('docker-images'),
            ], ['aws-cicd-docker']);
        }

        $this->publishes([
            __DIR__.'/config/aws-cicd.php' => config_path('aws-cicd.php'),
        ], ['aws-cicd-config']);
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            // Docker
            BuildBaseImagesCommand::class,
            BuildAppImagesCommand::class,
            InstallCommand::class,
            BuildUxmalTechConfigCommand::class,
            ComposeUpCommand::class,
            // Devtools
            AddBackOfficeUICommand::class,
            BuildInfrastructureConfigCommand::class,
            BuildUxmalTechConfigCommand::class,
            BuildAwsEcrConfigCommand::class,
            BuildAwsVpcConfigCommand::class,
            BuildAwsEcsConfigCommand::class,
            BuildDockerizedConfigCommand::class,
            TestCommand::class,
            // AWS
            ECRPushToRepositoryCommand::class,
            ECRListRepositoriesCommand::class,
            ECRDockerLoginCommand::class,
            DeployInfrastructureCommand::class,
            // GitHub
            CreateBranchCommand::class,
            CommitPushCommand::class,
            CreatePullRequestCommand::class,
            ResetTokenCommand::class,
        ];
    }
}
