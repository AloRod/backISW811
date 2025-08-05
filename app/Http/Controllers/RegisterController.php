<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Validator;

class RegisterController extends Controller
{
     public function index()
  {
    return view('register');
  }

  public function store(Request $request)
  {
    $data = $request->only('first_name', 'last_name', 'email', 'password');

    $validator = Validator::make($data, [
      'first_name' => 'required|string|min:3|max:255',
      'last_name' => 'required|string|min:3|max:255',
      'email' => 'required|string|email|unique:users',
      'password' => 'required|string|min:8|max:255',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }


    $user = User::create($data);

    return response()->json(['data' => $user,], 201);
  }
}
