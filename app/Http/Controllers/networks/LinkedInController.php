<?php

namespace App\Http\Controllers\networks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;

class LinkedInController extends Controller
{
  public function getLinkedInAuthorize() //genera un link de autorización para LinkedIn
  {
    $query = http_build_query([
      'response_type' => 'code',
      'client_id' => env("LINKEDIN_CLIENT_ID"),
      'redirect_uri' => env("LINKEDIN_REDIRECT_URI"),
      'state' => bin2hex(random_bytes(8)),
      'scope' => 'email,openid,profile,w_member_social'
    ]);

    return response()->json(['link' => "https://www.linkedin.com/oauth/v2/authorization?$query"], 200);
  }

  public function getAccessToken(Request $request) //obtiene el token de acceso de LinkedIn
  {
    $user_id = $request->input('user_id');
    $response = Http::withHeaders([
      'Content-Type' => 'application/x-www-form-urlencoded',
    ])
      ->asForm()
      ->post('https://www.linkedin.com/oauth/v2/accessToken', [
        'grant_type' => 'authorization_code',
        'code' => $request->input('code'),
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri' => env('LINKEDIN_REDIRECT_URI'),
      ]);

    if ($response->ok()) {
      $data = [
        'user_id' => $user_id,
        'platform' => 'linkedin',
        'access_token' => $response->json()['access_token'],
        'status' => true,
      ];
      Connection::create($data);
    }

    return $response->json();
  }

  public function createPost($user_id, $text) //crea un post en LinkedIn
  {
    $access_token = Connection::where('user_id', $user_id)
      ->where('platform', 'linkedin')
      ->first()
      ->access_token;

    $author = Http::withToken($access_token)->get('https://api.linkedin.com/v2/userinfo')['sub'];

    $body = [
      "author" => "urn:li:person:{$author}",
      "lifecycleState" => "PUBLISHED",
      "specificContent" => [
        "com.linkedin.ugc.ShareContent" => [
          "shareCommentary" => [
            "text" => $text
          ],
          "shareMediaCategory" => "NONE"
        ]
      ],
      "visibility" => [
        "com.linkedin.ugc.MemberNetworkVisibility" => "PUBLIC"
      ]
    ];

    $post = Http::withToken($access_token)->post('https://api.linkedin.com/v2/ugcPosts', $body);

    return $post->json();
  }
}
