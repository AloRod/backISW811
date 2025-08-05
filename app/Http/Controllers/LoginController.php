<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;


class LoginController extends Controller
{
     public function index()
  {
    return view('login');
  }

  public function authenticate(Request $request)
  {
    $credentials = $request->only('email', 'password');

    $user = User::where('email', $credentials['email'])->first();

    if (!($user && $user->checkPassword($credentials['password']))) {
      return response()->json(['error' => 'Invalid credentials.',], 401);
    }

    return response()->json(['data' => $user], 200);
  }

}
