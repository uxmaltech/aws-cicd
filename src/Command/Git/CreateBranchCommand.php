<?php

namespace Uxmal\Devtools\Command\Git;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CreateBranchCommand extends Command
{
    protected $signature = 'git:create-branch {name}';
    protected $description = 'Create a new branch in multiple directories';

    public function handle()
    {
        $branchName = $this->argument('name');

        $directories = config('uxmaltech.git.repositories');

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                $this->error("The directory `{$directory}` does not exist.");
                continue;
            }

            $this->info("Creating branch '{$branchName}' in '{$directory}'...");

            $process = new Process(['git', '-C', $directory, 'checkout', '-b', $branchName]);
            $process->run();

            // Executes after the command finishes
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->info($process->getOutput());
        }

        $this->info('Branch creation process completed.');
    }
}
