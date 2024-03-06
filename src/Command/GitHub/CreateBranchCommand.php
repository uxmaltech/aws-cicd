<?php

namespace Uxmal\Devtools\Command\GitHub;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CreateBranchCommand extends Command
{
    protected $signature = 'github:create-branch {name}';

    protected $description = 'Create a new branch in multiple directories';

    public function handle(): void
    {
        $branchName = $this->argument('name');

        $repositories = config('uxmaltech.git.repositories');

        foreach ($repositories as $directory) {
            if (! is_dir($directory)) {
                $this->error("The directory `$directory` does not exist.");
                continue;
            }

            $this->info("Creating branch '$branchName' in '$directory'...");

            $process = new Process(['git', '-C', $directory, 'checkout', '-b', $branchName]);
            $process->run();

            // Executes after the command finishes
            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
            $this->info($process->getOutput());
        }

        touch('./'.$branchName.'.message');

        $this->info('Branch creation process completed.');
    }
}
