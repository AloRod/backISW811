<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

//permite gestionar las conexiones de los usuarios con las diferentes plataformas de redes sociales
class ConnectionController extends Controller
{
  public function index()
  {
    $connections = Connection::all();
    return response()->json(['data' => $connections], 200);
  }

  public function store(Request $request)
  {
    $data = $request->only('user_id', 'platform', 'access_token', 'status');

    $validator = Validator::make($data, [
      'user_id' => 'required|integer',
      'platform' => 'required',
      'access_token' => 'required',
      'status' => 'required|boolean',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $connection = Connection::create($data);

    return response()->json(['data' => $connection], 201);
  }

  public function getPlatformsStatusByUserId($user_id)
  {
    $connections = Connection::select('id', 'platform', 'status')
      ->where('user_id', $user_id)
      ->get();

    if (!$connections) {
      return response()->json(['message' => 'Connections not found'], 404);
    }

    return response()->json(['data' => $connections], 200);
  }

  public function destroy($id)
  {
    $connection = Connection::find($id);

    if (!$connection) {
      return response()->json(['message' => 'Connection not found'], 404);
    }

    $connection->delete();

    return response()->json(null, 204);
  }

}

