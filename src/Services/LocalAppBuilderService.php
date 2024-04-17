<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Traits\GeneralUtils;
use Uxmal\Devtools\Interfaces\AppBuilderInterface;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class LocalAppBuilderService implements AppBuilderInterface
{


    use GeneralUtils;

    protected $validRepositories = [];

    function __construct()
    {
        $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
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
                $this->buildComposerInstall($repository_path);
                break;
            case 'uxmaltech/backoffice-ui-site':
                $$this->updateRepository($repository_path);
                $this->buildNpm($repository_path);
                break;
            case 'uxmaltech/backoffice-ui':
                $this->updateRepository($repository_path);
                $this->buildComposerInstall($repository_path);
                break;
        }
    }

    private function updateRepository(string $repository_path, string $branch = ''): void
    {
        try {
            $this->runCmd(['git', '-C', $repository_path, 'pull', 'origin ', $branch]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error pulling changes');
        }
    }

    // Build ui-npm project
    // @param string $repository_path
    // @return void
    // @throws \Exception
    private function buildNpm(string $repository_path, bool $build = false): void
    {
        try {
            //Run composer install
            $env = ['PATH' => '/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin'];
            // Install dependencies
            $this->runCmd(['npm', '--prefix' . $repository_path, 'ci'], $env);
            if ($build) {
                // Build project
                $this->runCmd(['npm', '--prefix' . $repository_path, 'run', 'build'], $env);
            }
        } catch (\Exception $e) {
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
            $this->runCmd(['composer', 'install', '--no-interaction', '--optimize-autoloader', '--working-dir=' . $repository_path], ['COMPOSER_HOME' => '$HOME/config/.composer']);
        } catch (\Exception $e) {
            throw new \Exception('error processing backoffice-ui');
        }
    }
}
