<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Traits\GeneralUtils;
use Uxmal\Devtools\Traits\GitTrait;
use Uxmal\Devtools\Interfaces\AppBuilderInterface;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class LocalAppBuilderService implements AppBuilderInterface
{


    use GeneralUtils;
    use GitTrait;

    protected $validRepositories = [];
    protected $runningOn = null;

    function __construct()
    {
        $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
        $this->runningOn = config('uxmaltech.name') ?? null;
    }
    public function build(string $repository): void
    {
        $repositories = $this->validRepositories;

        if (!in_array($repository, array_keys($repositories))) {
            throw new \Exception('Repository not found in valid repositories.');
        }

        // Get the path of the repository
        $repository_path = $repositories[$repository];
        // Run the build process
        switch ($repository) {
            case 'uxmaltech/backoffice-ui-npm':
                // TODO: Run git pull
                $this->updateRepository($repository_path);
                $this->buildNpm($repository_path);
                break;
            case 'uxmaltech/backoffice-ui-site':
                Log::info('Updating repository');
                $this->updateRepository($repository_path);
                Log::info('Running npm build');
                $this->buildNpm($repository_path, true);
                Log::info('Running composer install');
                $this->buildComposerInstall($repository_path);
                Log::info('Build process finished');
                break;
            case 'uxmaltech/backoffice-ui':
                $this->updateRepository($repository_path);
                $this->buildComposerInstall($repository_path);
                break;
            default:
                throw new \Exception('Build process not implemented for {$repository}  in local mode');
        }
    }

    // Build ui-npm project
    // @param string $repository_path
    // @return void
    // @throws \Exception
    private function buildNpm(string $repository_path, bool $build = false): void
    {
        $env = $_SERVER;
        try {
            //Run composer instal
            if (!isset($env['PATH'])) {
                //TODO: fix node binary path
                $env['PATH'] = '/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:/home/edgardo/.nvm/versions/node/v20.11.1/bin';
            }

            //Install dependencies
            $npmInstall = $this->runCmd(['npm', '--prefix' . $repository_path, 'ci'], $env);
            Log::debug($npmInstall);
            if ($build) {
                //Build project
                $npmBuild = $this->runCmd(['npm', '--prefix' . $repository_path, 'run', 'build'], $env);
                Log::debug($npmBuild);
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception($e->getMessage());
        }
    }

    // Build backoffice-ui project
    // @param string $repository_path
    // @return void
    // @throws \Exception
    private function buildComposerInstall(string $repository_path): void
    {
        try {
            // Run composer install
            $composer = $this->runCmd(['composer', 'update', '--no-interaction', '--optimize-autoloader', '--working-dir=' . $repository_path], ['COMPOSER_HOME' => '$HOME/config/.composer']);
            Log::debug($composer);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error processing backoffice-ui');
        }
    }
}
