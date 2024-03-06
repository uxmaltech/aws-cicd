<?php

namespace Uxmal\Devtools\Command\GitHub;

use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ResetTokenCommand extends Command
{
    protected $signature = 'github:reset-token {user} {token?}';
    protected $description = 'Reset GitHub token in the origin remote URL for multiple repositories.';

    public function handle(): void
    {
        $token = $this->argument('token');
        if( empty($token)){
            $token = config('uxmaltech.git.token');
        }
        if(empty($token)){
            $this->error('Please set your GitHub token in the `github_token` key of the `git` configuration in the `uxmaltech.php` file. or pass it as an argument to the command.');
            return;
        }

        $user = $this->argument('user');

        $repositories = config('uxmaltech.git.repositories');

        foreach ($repositories as $directory) {
            $repositoryPath = realpath($directory);
            if (!is_dir($repositoryPath)) {
                $this->error("The directory `$repositoryPath` does not exist.");
                continue;
            }

            $this->info("Updating token for repository at '$repositoryPath'...");

            // Get the current origin URL
            $process = new Process(['git', '-C', $repositoryPath, 'config', '--get', 'remote.origin.url']);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $currentUrl = trim($process->getOutput());
            $updatedUrl = $this->replaceTokenInUrl($currentUrl, $user, $token);

            $updateProcess = new Process(['git', '-C', $repositoryPath, 'remote', 'set-url', 'origin', $updatedUrl]);
            $updateProcess->run();

            if (!$updateProcess->isSuccessful()) {
                throw new ProcessFailedException($updateProcess);
            }

            $this->info("Token updated for repository at '$repositoryPath'.");
        }

        $this->info('GitHub token reset complete for all specified repositories.');
    }

    private function replaceTokenInUrl($url, $user, $newToken)
    {
        $pattern = '/https:\/\/(.*)(github.com\/.*)/i';
        $replacement = "https://$user:$newToken@$2";
        return preg_replace($pattern, $replacement, $url);
    }
}
