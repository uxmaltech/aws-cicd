<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Interfaces\AppBuilderInterface;

class LocalAppBuilder implements AppBuilderInterface
{
    protected $validRepositories = [];

    function __construct()
    {
        $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
    }
    public function build(string $repository): void
    {
        if (!in_array($repository, array_keys($repositories))) {
            throw new \Exception('Repository not found in valid repositories.');
        }

        // Get the path of the repository
        $repository_path = $repositories[$repository];
        Log::debug('repositories: ' . $this->validRepositories);
        // Run the build process
        switch ($repository) {
            case 'uxmaltech/backoffice-ui-npm':
                // TODO: Run git pull
                $process = Process::run('git -C ' . $repository_path . 'pull origin');

                break;
            case 'uxmaltech/backoffice-ui':
                // TODO: Run composer install/update for backoffice-ui
                $process = Process::run('git -C ' . $repository_path . 'pull origin');

                break;
        }
    }
}
