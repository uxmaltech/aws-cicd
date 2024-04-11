<?php



namespace Uxmal\Devtools\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class GithubWebhookController extends Controller
{
  public function handle(Request $request)
  {
    $payload = json_decode($request->getContent(), true);

    if ($payload['action'] === 'opened') {
      $this->info('Pull request opened.');
    }

    return response()->json(['status' => 'ok']);
  }

  public function test()
  {
    return response()->json(['status' => 'ok!!!']);
  }
}
