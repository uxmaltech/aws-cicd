<?php

namespace Uxmal\Devtools\Services;

use Uxmal\Devtools\Interfaces\AppBuilderInterface;
use Illuminate\Support\Facades\Process;

class LocalAppBuilderService implements AppBuilderInterface
{
    protected $validRepositories = [];

    function __construct()
    {
        $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
    }
    public function build(string $repository): void
    {
        $repositories = $this->validRepositories;
        if (!in_array($repository, array_keys($repositories))) {
            throw new \InvalidArgumentException('Repository not found in valid repositories.');
        }

        // Get the path of the repository
        $repository_path = $repositories[$repository];
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
        if ($process->errorOutput()) {
            echo "Build process completed successfully.\n";
        } else {
            throw new \Exception($process->errorOutput());
        }
    }
}
