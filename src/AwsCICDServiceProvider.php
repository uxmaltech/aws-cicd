<?php

namespace Uxmal\AwsCICD;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Uxmal\AwsCICD\Command\Docker\BuildBaseImagesCommand;
use Uxmal\AwsCICD\Command\Docker\ComposeBuildCommand;
use Uxmal\AwsCICD\Command\InstallCommand;
use Uxmal\AwsCICD\Command\Uxmaltech\AddBackOfficeUICommand;

class AwsCICDServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->configurePublishing();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Docker
                BuildBaseImagesCommand::class,
                ComposeBuildCommand::class,
                InstallCommand::class,
                // Uxmaltech
                AddBackOfficeUICommand::class,
            ]);
        }
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../docker-images' => $this->app->basePath('docker-images'),
            ], ['aws-cicd-docker']);
        }

        $this->publishes([
            __DIR__ . '/config/aws-cicd.php' => config_path('aws-cicd.php'),
        ], ['aws-cicd-config']);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            // Docker
            BuildBaseImagesCommand::class,
            ComposeBuildCommand::class,
            InstallCommand::class,
            // Uxmaltech
            AddBackOfficeUICommand::class,
        ];
    }
}
