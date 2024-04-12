<?php



namespace Uxmal\Devtools\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class GithubWebhookController extends Controller
{

  protected $validRepositories = [];

  function __construct()
  {

    $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
    Log::debug('Valid repositories: ' . json_encode($this->validRepositories));
  }

  // Return the trigger event
  // @param array $header
  // Possible values inside the `x-github-event` are
  // pull_request, push, release, workflow_job, issue, create
  private function getEvent($headers)
  {
    $event = $headers['x-github-event'];
    return $event[0];
  }

  private function isMainBranch($ref)
  {

    return $ref == "refs/heads/main" || $ref == "refs/heads/master";
  }

  public function handle(Request $request)
  {
    $payload = json_decode($request->getContent(), true);
    $headers = $request->header();
    $event = $this->getEvent($headers);

    $ref = $payload['ref'] ?? null;
    $repository = $payload['repository']['full_name'] ?? null;

    if ($event == 'pull_request') {
      $pull_request = $payload['pull_request'];
      $is_merged = $pull_request['merged'] ?? false;
      $merged_by = '';
      if ($is_merged) {
        // TODO: DO SOMETHING
        $merged_by = $pull_request['merged_by']['login'] ?? null;
        $number = $pull_request['number'];

        Log::debug('Running build for repository: ' . $repository);
        $this->runBuild($repository);
      }
    } elseif ($event == "push" && $this->isMainBranch($ref)) {
      Log::info(
        'Push to master detected',
      );
    } else {
      Log::info('Event not handled.', [
        'ref' => $ref,
        'repository' => $repository,
        'event' => $event,
        'payload' => $payload
      ]);
    }
    return response()->setStatusCode(200);
  }

  // Run the build process for a given repository
  // @param string $repository
  // @return void
  // @throws Exception
  private function runBuild(string $repository)
  {
    try {
      // TODO:: Define the list of valid modes
      $mode = strtolower(config('uxmaltech.mode') ?? '');
      $builder = null;

      switch ($mode) {
        case 'docker':
          $builder = new \Uxmal\Devtools\Services\DockerAppBuilder();
          break;
        case 'aws':
          $builder = new \Uxmal\Devtools\Services\AwsAppBuilder();
          break;
        case 'local':
        case 'dev':
          $builder = new \Uxmal\Devtools\Services\LocalAppBuilder();
      }
      if ($mode == 'docker') {
      } else {
        $builder = new \Uxmal\Devtools\Services\LocalAppBuilder();
      }
      $builder->build($repository);
    } catch (\Exception $e) {
      Log::error('Error building repository: ' . $repository, [
        'error' => $e->getMessage()
      ]);
    }
  }
}
