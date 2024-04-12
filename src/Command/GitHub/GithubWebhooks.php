<?php

namespace Uxmal\Devtools\Command\Github;

use Github\AuthMethod;
use Illuminate\Console\Command;

class GithubWebhooks extends Command
{
  protected $signature = 'devtools:github-gen-webhooks';

  public function __construct()
  {
    parent::__construct();
  }

  public function handle(): void
  {

    $this->info('Configurando webhooks de Github...');
    // $app = config('app.name');
    $app = config('uxmaltech.domain');
    // $token = config('github.token');
    $token = config('uxmaltech.github.token');
    // die(var_dump($app, $token));

    $this->createWebhook('infraestructura', 'https://infraestructura.' . $app . '/github-webhook');

    $this->info('Webhooks configurados correctamente.');
  }

  private function createWebhook($repo, $url)
  {
    $this->info('Creando webhook para el repositorio ' . $repo . '...');

    $client = new \Github\Client();
    $token = config('uxmaltech.github.token');
    $client->authenticate($token, AuthMethod::ACCESS_TOKEN);

    // $client->api('repo')->hooks()->all('twbs', 'bootstrap');
    $hooks = $client->api('repo')->hooks()->all('twbs', 'bootstrap');
    // $client->api('repo')->hooks()->create(
    //   $this->config['github']['organization'],
    //   $repo,
    //   [
    //     'name' => 'web',
    //     'config' => [
    //       'url' => $url,
    //       'content_type' => 'json',
    //       'secret' => $this->config['github']['webhook_secret'],
    //       'insecure_ssl' => '0',
    //     ],
    //     'events' => ['push'],
    //     'active' => true,
    //   ]
    // );

    // $this->info('Webhook creado correctamente.');
  }

  // private function info($message)
  // {
  //   echo "\033[32m" . $message . "\033[0m\n";
  // }
}
