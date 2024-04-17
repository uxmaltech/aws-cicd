<?php



namespace Uxmal\Devtools\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class GithubWebhookController extends Controller
{

  protected $validRepositories = [];

  function __construct()
  {

    $this->validRepositories = config('uxmaltech.git.repositories') ?? [];
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
    try {
      $payload = json_decode($request->getContent(), true);
      $headers = $request->header();
      $event = $this->getEvent($headers);

      $ref = $payload['ref'] ?? null;
      $repository = $payload['repository']['full_name'] ?? null;

      if ($event == 'pull_request') {
        $pull_request = $payload['pull_request'];
        $is_merged = $pull_request['merged'] ?? false;
        $merged_by = '';

        // If pull request is merged and the destination branch is main
        if ($is_merged && $pull_request['base']['ref'] == 'main') {
          $merged_by = $pull_request['merged_by']['login'] ?? null;
          $number = $pull_request['number'];
          // TODO: Exec async process
          $this->runDeploy($repository);
        }
      } elseif ($event == "push" && $this->isMainBranch($ref)) {
        Log::info(
          'Push to master detected',
        );
      } else {
        // Other events
      }
      //return response()->json(['status' => 'ok'], 200);
    } catch (\Exception $e) {
      Log::error('Error handling webhook', [
        'error' => $e->getMessage()
      ]);
      //return response()->json(['status' => 'error'], 500);
    } finally {
      return response()->json(['status' => 'ok'], 200);
    }
  }

  // Run the build process for a given repository
  // @param string $repository
  // @return void
  // @throws Exception
  private function runDeploy(string $repository): void
  {
    $repository = 'uxmaltech/backoffice-ui';
    $repository = 'uxmaltech/backoffice-ui-npm';
    $repository = 'uxmaltech/backoffice-ui-site';
    try {
      // TODO:: Define the list of valid modes
      $mode = strtolower(config('uxmaltech.mode') ?? 'local');
      //$builder = null;

      switch ($mode) {
        case 'docker':
          $builder = new \Uxmal\Devtools\Services\DockerAppBuilderService();
          break;
        case 'aws':
          $builder = new \Uxmal\Devtools\Services\AwsAppBuilderService();
          break;
        case 'local':
        case 'dev':
        default:
          $builder = new \Uxmal\Devtools\Services\LocalAppBuilderService();
          break;
      }
      Log::debug('Building repository ' . $repository . ' with mode ' . $mode);
      $builder->build($repository);
    } catch (\Exception $e) {
      throw new \Exception($e->getMessage());
    }
  }
}
