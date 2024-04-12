<?php



namespace Uxmal\Devtools\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class GithubWebhookController extends Controller
{

  // Return the trigger event
  // @param array $header
  // Possible values inside the `x-github-event` are
  // pull_request, push, release, workflow_job, issue
  private function getEvent($headers)
  {
    $event = $headers['x-github-event'];
    return $event[0];
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

        Log::info('Pull request merged.', [
          'pull_request' => $payload['pull_request'],
          'is_merged' => $is_merged,
          'merged_by' => $merged_by,
          'number' => $number ?? 0
        ]);
      }
    } else {
      Log::info('Event not handled.', [
        'ref' => $ref,
        'repository' => $repository,
        'event' => $event,
        'payload' => $payload
      ]);
    }
    return response()->json(['status' => 'ok'])->setStatusCode(200);
  }
}
