<?php

namespace App\Http\Controllers\networks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

class RedditController extends Controller
{
    public function getRedditAuthorize()
  {
    $query = http_build_query([
      'client_id' => env('REDDIT_CLIENT_ID'),
      'response_type' => 'code',
      'state' => bin2hex(random_bytes(8)),
      'redirect_uri' => env('REDDIT_REDIRECT_URI'),
      'duration' => 'permanent',
      'scope' => 'identity,submit',
    ]);

    $link = "https://www.reddit.com/api/v1/authorize?$query";

    return response()->json(['link' => $link], 200);
  }

  public function getAccessToken(Request $request)
  {
    $user_id = $request->input('user_id');
    $redditClientId = env('REDDIT_CLIENT_ID');
    $redditClientSecret = env('REDDIT_CLIENT_SECRET');
    $response = Http::asForm()
      ->withBasicAuth($redditClientId, $redditClientSecret)
      ->post('https://www.reddit.com/api/v1/access_token', [
        'grant_type' => 'authorization_code',
        'code' => $request->input('code'),
        'redirect_uri' => env('REDDIT_REDIRECT_URI'),
      ]);

    if ($response->ok()) {
      $data = [
        'user_id' => $user_id,
        'platform' => 'reddit',
        'access_token' => $response->json()['access_token'],
        'status' => true,
      ];
      Connection::create($data);
    }

    return response()->json(['access_token' => $response->json()['access_token']], 200);
  }

  public function createPost($user_id, $text)
  {
    $access_token = Connection::where('user_id', $user_id)
      ->where('platform', 'reddit')
      ->first()
      ->access_token;

    $sr = Http::withToken($access_token)->get('https://oauth.reddit.com/api/v1/me')['subreddit']['display_name'];

    $body = [
      "kind" => "self",
      "sr" => $sr,
      "title" => $text
    ];

    $post = Http::withToken($access_token)
      ->withHeaders(['User-Agent' => 'TrodoTC'])
      ->asForm()
      ->post('https://oauth.reddit.com/api/submit', $body);

    return $post;
  }

}
