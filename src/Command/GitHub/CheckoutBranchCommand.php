<?php

namespace Uxmal\Devtools\Command\GitHub;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CheckoutBranchCommand extends Command
{
    protected $signature = 'github:checkout-branch {--branch=main}';

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

            $branch = $this->option('branch', 'main');

            $this->info("Processing '$repositoryPath'...");

            // Get the current branch name
            $process = new Process(['git', '-C', $repositoryPath, 'rev-parse', '--abbrev-ref', 'HEAD']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $currentBranch = trim($process->getOutput());

            if ($currentBranch === $branch) {
                $this->info("Already on '$branch' branch in '$repositoryPath'. Just pulling the latest changes.");
            } else {
                // Delete the current branch
                $deleteProcess = new Process(['git', '-C', $repositoryPath, 'branch', '-d', $currentBranch]);
                $deleteProcess->run();
                // No need to check if deletion is successful as we're switching to $branch regardless
            }

            // Checkout the $branch branch
            $checkoutProcess = new Process(['git', '-C', $repositoryPath, 'checkout', $branch]);
            $checkoutProcess->run();
            if (!$checkoutProcess->isSuccessful()) {
                $this->error("Failed to checkout $branch branch in '$repositoryPath'.");
            }

            // Pull the latest changes
            $pullProcess = new Process(['git', '-C', $repositoryPath, 'pull', 'origin', $branch]);
            $pullProcess->run();
            if (!$pullProcess->isSuccessful()) {
                $this->error("Failed to pull the latest changes for $branch branch in '$repositoryPath'.");
            }

            $this->info("Updated $branch branch in '$repositoryPath'.");
        }

        $this->info($branch . ' branch checkout and update complete for all specified repositories.');
    }
}
