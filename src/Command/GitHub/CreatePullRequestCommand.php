<?php

namespace Uxmal\Devtools\Command\GitHub;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CreatePullRequestCommand extends Command
{
    protected $signature = 'github:create-pull-request {branch}';

    protected $description = 'Create a GitHub pull request for multiple repositories with the current branch against main, skip if up to date.';

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    public function handle(): void
    {
        $repositories = config('uxmaltech.git.repositories');
        $branch = $this->argument('branch');

        $githubToken = config('uxmaltech.git.token');
        if ($githubToken == 'YOUR_GITHUB_TOKEN') {
            $this->error('Please set your GitHub token in the `github_token` key of the `git` configuration in the `uxmaltech.php` file.');
            return;
        }

        $this->info('Committing and pushing changes repository current branches...');
        $this->call('github:commit-push');

        $ini_data = [];
        if (file_exists('./' . $branch . '.message.ini')) {
            $ini_data = parse_ini_file('./' . $branch . '.message.ini', true);
        }


        foreach ($repositories as $repository => $repositoryPath) {
            if (isset($ini_data[$repository])) {
                $body = "";
                foreach ($ini_data[$repository] as $key => $value) {
                    if (str_starts_with($key, 'line')) {
                        $body .= $value . "\n";
                    }
                }
            } else {
                $body = $this->ask('Please enter the pull request description');
            }

            // Assume $repositoryPath is the path to the repository directory
            $repositoryPath = realpath($repositoryPath);

            // Check if the repository path exists
            if (!is_dir($repositoryPath)) {
                $this->error("The directory for repository '{$repository}' does not exist.");
                continue;
            }

            // Get the current branch name
            $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $repositoryPath);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $head = trim($process->getOutput());
            $base = 'main';

            // Check for differences between the current branch and the base branch
            $diffProcess = new Process(['git', 'diff', '--quiet', $base, $head], $repositoryPath);
            $diffProcess->run();

            // If the exit code is 0, there are no differences
            if ($diffProcess->isSuccessful()) {
                $this->info("No changes detected for '$repository' between branches '$head' and '$base'. Skipping pull request creation.");
            } else {
                $this->info("Creating pull request for '$repository' from '$head' to '$base' => https://api.github.com/repos/$repository/pulls ...");
                $client = new Client();

                try {
                    $response = $client->post("https://api.github.com/repos/$repository/pulls", [
                        'headers' => [
                            'Accept' => 'application/vnd.github+json',
                            'Authorization' => 'Bearer ' . $githubToken,
                            'Content-Type' => 'application/json',
                            'X-GitHub-Api-Version' => '2022-11-28',
                        ],
                        'json' => [
                            'title' => $branch,
                            'head' => $head,
                            'base' => $base,
                            'body' => $body,
                        ],
                    ]);

                    if ($response->getStatusCode() == 201) {
                        $this->info("Pull request created successfully for $repository from $head to $base!");
                    } else if ($response->getStatusCode() == 422) {
                        $this->info("Pull request already exists for $repository from $head to $base!");
                    } else {
                        $this->error("Failed to create the pull request for $repository.");
                    }
                } catch (\GuzzleHttp\Exception\ClientException $e) {
                    switch( $e->getCode() ){
                        case 401:
                            $this->error("Unauthorized: Please check your GitHub token.");
                            break;
                        case 404:
                            $this->error("Repository not found: Please check the repository name.");
                            break;
                        case 422:
                            $this->info("Pull request already exists for $repository from $head to $base!");
                            break;
                        default:
                            $this->error("An error occurred while creating the pull request: " . $e->getMessage());
                            $this->error("error code: " . $e->getCode());
                    }
                } catch (\Exception $e) {
                    $this->error("An error occurred while creating the pull request: " . $e->getMessage());
                }
            }
        }
    }
}
