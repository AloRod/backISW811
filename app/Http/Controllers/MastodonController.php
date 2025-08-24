<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MastodonController extends Controller
{
  public function getMastodonAuthorize()
  {
    $query = http_build_query([
      'response_type' => 'code',
      'client_id' => env("MASTODON_CLIENT_ID"),
      'redirect_uri' => env("MASTODON_REDIRECT_URI"),
      'state' => bin2hex(random_bytes(8)),
      'scope' => 'read write'
    ]);

    $mastodonInstance = env("MASTODON_INSTANCE", "mastodon.social");
    return response()->json(['link' => "https://{$mastodonInstance}/oauth/authorize?$query"], 200);
  }

  public function getAccessToken(Request $request)
  {
    $user_id = $request->input('user_id');
    $mastodonInstance = env("MASTODON_INSTANCE", "mastodon.social");

    $response = Http::withHeaders([
      'Content-Type' => 'application/x-www-form-urlencoded',
    ])
      ->asForm()
      ->post("https://{$mastodonInstance}/oauth/token", [
        'grant_type' => 'authorization_code',
        'code' => $request->input('code'),
        'client_id' => env('MASTODON_CLIENT_ID'),
        'client_secret' => env('MASTODON_CLIENT_SECRET'),
        'redirect_uri' => env('MASTODON_REDIRECT_URI'),
      ]);

    if ($response->ok()) {
      $data = [
        'user_id' => $user_id,
        'platform' => 'mastodon',
        'access_token' => $response->json()['access_token'],
        'status' => true,
      ];
      Connection::create($data);
    }

    return $response->json();
  }

  public function createPost($user_id, $text)
  {
    $access_token = Connection::where('user_id', $user_id)
      ->where('platform', 'mastodon')
      ->first()
      ->access_token;

    $mastodonInstance = env("MASTODON_INSTANCE", "mastodon.social");

    $body = [
      'status' => $text,
      'visibility' => 'public'
    ];

    $post = Http::withToken($access_token)
      ->withHeaders([
        'Content-Type' => 'application/json',
      ])
      ->post("https://{$mastodonInstance}/api/v1/statuses", $body);

    return $post->json();
  }

  public function getAccountInfo($user_id)
  {
    $access_token = Connection::where('user_id', $user_id)
      ->where('platform', 'mastodon')
      ->first()
      ->access_token;

    $mastodonInstance = env("MASTODON_INSTANCE", "mastodon.social");

    $response = Http::withToken($access_token)
      ->get("https://{$mastodonInstance}/api/v1/accounts/verify_credentials");

    return $response->json();
  }
}
