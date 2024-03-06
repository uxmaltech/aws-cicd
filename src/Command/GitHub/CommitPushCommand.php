<?php

namespace Uxmal\Devtools\Command\GitHub;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CommitPushCommand extends Command
{
    protected $signature = 'github:commit-push';
    protected $description = 'Commit and push changes in the current branch for multiple directories';

    public function handle(): void
    {

        $currentDate = Carbon::now()->format('Y-m-d H:i:s');
        $repositories = config('uxmaltech.git.repositories');

        foreach ($repositories as $directory) {
            $repositoryPath = realpath($directory);
            if (!is_dir($repositoryPath)) {
                $this->error("The directory `$repositoryPath` does not exist.");
                continue;
            }

            $this->info("Processing '$repositoryPath'...");

            // Check if there are changes to commit
            $statusProcess = new Process(['git', '-C', $repositoryPath, 'status', '--porcelain']);
            $statusProcess->run();

            if (!$statusProcess->isSuccessful()) {
                throw new ProcessFailedException($statusProcess);
            }

            if (empty(trim($statusProcess->getOutput()))) {
                $this->info("No changes to commit in '$repositoryPath'. Skipping...");
                continue;
            }

            $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $repositoryPath);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $currentBranch = trim($process->getOutput());

            $commitMessage = $currentDate . "\nCommit branch " .$currentBranch;

            // Add all changes
            $this->executeProcess(['git', '-C', $repositoryPath, 'add', '.']);

            // Commit changes
            $this->executeProcess(['git', '-C', $repositoryPath, 'commit', '-m', $commitMessage]);

            // Push changes
            $this->executeProcess(['git', '-C', $repositoryPath, 'push', '-u', 'origin', $currentBranch]);

            $this->info("Changes in '$repositoryPath' have been committed and pushed.");
        }

        $this->info('All changes have been committed and pushed.');
    }

    private function executeProcess(array $command): void
    {
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if ($output = $process->getOutput()) {
            $this->info($output);
        }
    }
}
