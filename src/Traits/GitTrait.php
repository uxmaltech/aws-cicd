<?php

namespace Uxmal\Devtools\Traits;

use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

trait GitTrait
{

    public function updateRepository(string $repository_path, string $branch = 'main'): void
    {
        try {

            $this->runCmd(['git', '-C', $repository_path, 'checkout', $branch]);
            //$ cithis->runCmd(['git', '-C', $repository_path, 'pull', 'origin', $branch]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error pulling changes');
        }
    }

    public function commitAndPushChanges(string $repository_path, string $branch = 'main', string $commitMessage = ''): void
    {
        try {
            $process = new Process(['git', '-C', $repository_path, 'status', '--porcelain']);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            if (empty(trim($process->getOutput()))) {
                Log::info("No changes to commit in '$repository_path'. Skipping...");
                return;
            }

            $process = new Process(['git', '-C', $repository_path, 'rev-parse', '--abbrev-ref', 'HEAD']);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            $currentBranch = trim($process->getOutput());

            $commitMessage = $commitMessage ?: 'Commit branch ' . $currentBranch;

            // Add all changes
            $this->runCmd(['git', '-C', $repository_path, 'add', '.']);

            // Commit changes
            $this->runCmd(['git', '-C', $repository_path, 'commit', '-m', $commitMessage]);

            // Push changes
            $this->runCmd(['git', '-C', $repository_path, 'push', 'origin', $branch]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error committing and pushing changes');
        }
    }
}
