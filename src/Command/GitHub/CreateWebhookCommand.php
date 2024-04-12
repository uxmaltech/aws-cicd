<?php

namespace Uxmal\Devtools\Command\GitHub;


use Illuminate\Console\Command;
use Github\Client;
use Illuminate\Support\Facades\Request;
use Exception;

class CreateWebhookCommand extends Command
{
    protected $signature = 'github:create-webhook {--repository=} {--url=}';
    protected $description = 'Create a webhook in multiple repositories.';

    //protected $organization = 'uxmaltech';
    protected $organization = 'EdgardoAcosta';

    public function handle(): void
    {

        $repository = $this->option('repository');
        $url = $this->option('url');


        $this->info('Creating webhook in multiple repositories...');

        if (empty($repository)) {
            $repository = config('uxmaltech.name');
        }
        if (empty($url)) {
            $url = "http://" . Request::server('REMOTE_ADDR');
        }

        $this->createWebhook($repository, $url);
    }

    private function createWebhook(string $repository, string $url): void
    {
        $this->info("Creating webhook in {$repository}...");

        try {
            $client = new \Github\Client();

            $token = config('uxmaltech.git.token');

            $client->authenticate($token, \Github\AuthMethod::ACCESS_TOKEN);
            $hooks = $client->api('repo')->hooks()->all($this->organization, $repository);

            foreach ($hooks as $hook) {
                if ($hook['config']['url'] == $url) {
                    throw new Exception("Webhook already exists in {$repository} with url {$url}.");
                }
            }

            $new_hook = $client->api('repo')->hooks()->create($this->organization, $repository, [
                'name' => 'web',
                'config' => [
                    'url' => $url,
                    'content_type' => 'json',
                    'secret' => config('uxmaltech.git.secret') ?? '',
                    'insecure_ssl' => '0',
                ],
                'events' => ['push', 'pull_request', 'create', 'deployment', 'release', 'issues', 'delete',],
                'active' => true,
            ]);
            $this->info("Webhook created in {$repository} with id {$new_hook['id']}");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
