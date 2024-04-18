<?php

namespace Uxmal\Devtools\Traits;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

trait GitTrait
{

    public function updateRepository(string $repositoryPath, string $branch = 'main'): void
    {
        try {
            $process = new Process(['git', '-C', $repositoryPath, 'pull', 'origin', $branch]);
            $process->setTimeout(120)->setIdleTimeout(15)->mustRun();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error pulling changes');
        }
    }

    public function commitAndPushChanges(string $repositoryPath, string $branch = 'main', string $commitMessage = ''): void
    {
        try {
            $process = new Process(['git', '-C', $repositoryPath, 'status', '--porcelain']);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            if (empty(trim($process->getOutput()))) {
                Log::info("No changes to commit in '$repositoryPath'. Skipping...");
                return;
            }

            $process = new Process(['git', '-C', $repositoryPath, 'rev-parse', '--abbrev-ref', 'HEAD']);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \Exception($process->getErrorOutput());
            }

            $currentBranch = trim($process->getOutput());

            $commitMessage = $commitMessage ?: 'Commit branch ' . $currentBranch;

            // Add all changes
            $this->runCmd(['git', '-C', $repositoryPath, 'add', '.']);

            // Commit changes
            $this->runCmd(['git', '-C', $repositoryPath, 'commit', '-m', $commitMessage]);

            // Push changes
            $this->runCmd(['git', '-C', $repositoryPath, 'push', 'origin', $branch]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            throw new \Exception('error committing and pushing changes');
        }
    }

    // Check if the repository has pending changes
    // @param string $repositoryPath
    // @return bool
    // @throws \ProcessFailedException
    public function hasPendingGitCommits(string $repositoryPath): bool
    {
        // Cambia al directorio de trabajo
        if (!is_dir($this->laravel->basePath() . '/.git')) {
            return false;
        }
        $process = Process::fromShellCommandline('git status --porcelain', $repositoryPath);

        try {
            $process->mustRun();

            // Obtener la salida del comando
            $output = $process->getOutput();

            // Si la salida está vacía, no hay cambios pendientes
            return !empty($output);
        } catch (ProcessFailedException $exception) {
            // Manejar la excepción si el proceso falla
            echo 'Error al ejecutar git status: ', $exception->getMessage();

            return false;
        }
    }
}
