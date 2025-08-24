<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\History;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HistoryController extends Controller
{
    //obtener datos
  public function index()
  {
    $histories = History::all();
    return response()->json(['data' => $histories], 200);
  }
  //guardar
  public function store(Request $request)
  {
    $data = $request->only('date', 'time', 'post_id', 'status');

    $historyData = $request->only('date', 'time', 'post_id', 'status');
    $historyData['post_id'] = $post->id;

    $validator = Validator::make($data, [
      'post_id' => 'required|integer',
      'status' => 'required|in:posted,scheduled,queue'
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    // se encarga de enviar el post a las redes sociales si el estado es 'posted'
    if ($request->status == 'posted') {
      $post = new PostController;
      $post->sendToNetworks($request->user_id, $request->social_network, $request->post_text);
    }

    $history = History::create($data);

    return response()->json(['data' => $history], 201);
  }

  public function updateStatus(Request $request, $id)
  {
    $data = $request->only('status');

    $validator = Validator::make($data, [
      'status' => 'required',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $history = History::find($id);

    if (!$history) {
      return response()->json(['message' => 'History not found'], 404);
    }

    $history->update($request->all());

    return response()->json(['data' => $history], 200);
  }

  public function getByUserId($userId)
  {
    $histories = History::join('posts', 'posts.id', '=', 'histories.post_id')
      ->select('posts.id', 'posts.post_text', 'histories.status', 'posts.social_network')
      ->selectRaw("CONCAT(histories.date, ' ', histories.time) AS date")
      ->where('posts.user_id', $userId)
      ->orderBy('histories.created_at')
      ->get();

    return response()->json(['data' => $histories], 200);
  }

  //Pendiente
  public function getQueueByUserId($userId)
  {
    $schedules = DB::table('schedules')
      ->select(DB::raw('ROW_NUMBER() OVER (ORDER BY date, time) AS id'), 'user_id', DB::raw('DATE_FORMAT(CONCAT(date, " ", time), "%Y-%m-%d %H:%i") AS `date`'))
      ->where('user_id', $userId)
      ->orderBy('date', 'asc')
      ->orderBy('time', 'asc');

    $histories = History::join('posts', 'posts.id', '=', 'histories.post_id')
      ->selectRaw('ROW_NUMBER() OVER (ORDER BY histories.created_at) AS id')
      ->select('posts.post_text', 'posts.social_network', 'histories.status', 'posts.user_id', 'histories.created_at')
      ->where('posts.user_id', $userId)
      ->where('status', 'queue')
      ->orderBy('histories.created_at');

    $results = '';



    return response()->json(['data' => $results], 200);
  }
}
