<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use Illuminate\Support\Facades\Validator;
class PostController extends Controller
{
    public function index()
  {
    $posts = Post::all();
    return response()->json(['data' => $posts], 200);
  }


  public function store(Request $request)
  {
    $data = $request->only('user_id','post_text','social_network');

    $validator = Validator::make($data, [
      'user_id' => 'required|integer',
      'post_text' => 'required',
      'social_network' => 'required',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $posts = Post::create($data);

    return response()->json(['data' => $posts], 201);
  }

  // se encarga de enviar el post a las redes sociales que se seleccionen
  //metodo intermediario para llamar a los controladores de las redes sociales
  public function sendToNetworks($user_id, $social_network, $text)
  {
    if (stripos($social_network, 'linkedin') !== false) {
      $linkedinController = new LinkedInController;
      $linkedinController->createPost($user_id, $text);
    }

    if (stripos($social_network, 'reddit') !== false) {
      $redditController = new RedditController;
      $redditController->createPost($user_id, $text);
    }

    if (stripos($social_network, 'mastodon') !== false) {
      $mastodonController = new MastodonController;
      $mastodonController->createPost($user_id, $text);
    }
  }
}
