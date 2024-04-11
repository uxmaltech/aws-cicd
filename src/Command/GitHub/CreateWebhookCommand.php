<?php

namespace Uxmal\Devtools\Command\GitHub;


use Illuminate\Console\Command;
use Github\Client;
use Github\AuthMethod;

// class_alias('Github\Client', 'GithubClient');

class CreateWebhookCommand extends Command
{
    protected $signature = 'github:create-webhook';
    protected $description = 'Create a webhook in multiple repositories.';

    public function handle(): void
    {


        $this->info('Creating webhook in multiple repositories...');

        $this->createWebhook('devtools', 'https://devtools.uxmal.io/github/webhook');
    }

    private function createWebhook(string $repository, string $url): void
    {
        $this->info("Creating webhook in {$repository}...");

        try {
            //$client = new \Github\Client();
            $client = new Client();

            $token = config('uxmaltech.github.token');

            $client->authenticate($token, \Github\AuthMethod::ACCESS_TOKEN);

            $hooks = $client->api('repo')->hooks()->all('uxmaltech', $repository);

            print_r($hooks);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
