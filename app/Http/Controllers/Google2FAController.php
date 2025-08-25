<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Validator;

class Google2FAController extends Controller
{

    //funcion para generar codigo QR
  public function qrGenerator($email)
  {
    $google2fa = new Google2FA();
    $secret_key = $google2fa->generateSecretKey();

    $qrCodeUrl = $google2fa->getQRCodeUrl(
      "SocialHub",
      $email,
      $secret_key
    );

    return ['url' => $qrCodeUrl, 'secret' => $secret_key];
  }
    //envia el enlace qr al front
  public function getQRCode($id)
  {

    $user = User::find($id);

    if (!$user) {
      return response()->json(['message' => 'User not found'], 404);
    }

    $data = [
      'two_factor_url' => $user->two_factor_url,
      'two_factor_enabled' => $user->two_factor_enabled,
    ];

    return response()->json(['data' => $data], 200);
  }
 
  //verificar si los datos coinciden 
  public function verify(Request $request)
  {
    $data = $request->only('code', 'user_id');

    $validator = Validator::make($data, [
      'code' => 'required',
      'user_id' => 'required',
    ]);

    $secret = $request->input('code');
    $user_id = $request->input('user_id');

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $user = User::find($user_id);

    if (!$user) {
      return response()->json(['error' => 'User not found'], 404);
    }

    $google2fa = new Google2FA();
    $valid = $google2fa->verifyKey($user->two_factor_code, $secret);

    if (!$valid) {
      return response()->json(['error' => 'Invalid code'], 401);
    }

    return response()->json(['data' => $user], 200);
  }
 //Para habilitar la autenticacion de dos pasos
  public function enable(Request $request, $id)
  {
    $data = $request->only('two_factor_enabled');

    $validator = Validator::make($data, [
      'two_factor_enabled' => 'required',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 422);
    }

    $user = User::find($id);

    if (!$user) {
      return response()->json(['message' => 'User not found'], 404);
    }

    $google2fa = new Google2FA();
    $valid = $user->two_factor_enabled ? true : $google2fa->verifyKey($user->two_factor_code, $request->input('verify_code'));

    if (!$valid) {
      return response()->json(['error' => 'Invalid code'], 401);
    }

    $user->update($data);

    return response()->json(['data' => 'Successfully updated'], 200);
  }
}

