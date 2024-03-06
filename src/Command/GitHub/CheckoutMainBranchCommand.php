<?php

namespace Uxmal\Devtools\Command\GitHub;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CheckoutMainBranchCommand extends Command
{
    protected $signature = 'github:checkout-main';
    protected $description = 'Delete the current branch (if not main), checkout, and update the main branch for multiple repositories.';

    public function handle(): void
    {
        $repositories = config('uxmaltech.git.repositories');

        foreach ($repositories as $directory) {
            $repositoryPath = realpath($directory);
            if (!is_dir($repositoryPath)) {
                $this->error("The directory `$repositoryPath` does not exist.");
                continue;
            }

            $this->info("Processing '$repositoryPath'...");

            // Get the current branch name
            $process = new Process(['git', '-C', $repositoryPath, 'rev-parse', '--abbrev-ref', 'HEAD']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $currentBranch = trim($process->getOutput());

            if ($currentBranch === 'main') {
                $this->info("Already on 'main' branch in '$repositoryPath'. Just pulling the latest changes.");
            } else {
                // Delete the current branch
                $deleteProcess = new Process(['git', '-C', $repositoryPath, 'branch', '-d', $currentBranch]);
                $deleteProcess->run();
                // No need to check if deletion is successful as we're switching to main regardless
            }

            // Checkout the main branch
            $checkoutProcess = new Process(['git', '-C', $repositoryPath, 'checkout', 'main']);
            $checkoutProcess->run();
            if (!$checkoutProcess->isSuccessful()) {
                throw new ProcessFailedException($checkoutProcess);
            }

            // Pull the latest changes
            $pullProcess = new Process(['git', '-C', $repositoryPath, 'pull', 'origin', 'main']);
            $pullProcess->run();
            if (!$pullProcess->isSuccessful()) {
                throw new ProcessFailedException($pullProcess);
            }

            $this->info("Updated 'main' branch in '$repositoryPath'.");
        }

        $this->info('Main branch checkout and update complete for all specified repositories.');
    }
}
