<?php

use App\Http\Controllers\Google2FAController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\networks\LinkedInController;
use App\Http\Controllers\networks\RedditController;
use App\Http\Controllers\networks\MastodonController;
use App\Http\Controllers\ConnectionController;

Route::get('/user', function () {
    return "Entra";
});

Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/register', [RegisterController::class, 'store']);

// RUTA PARA VER Y GUARDAR LOS POSTS
Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class,'store']);

//Rutas para horarios de publicacion
Route::get('/schedules/{id}', [ScheduleController::class, 'show']);
Route::get('/schedules', [ScheduleController::class, 'index']);
Route::post('/schedules', [ScheduleController::class,'store']);
Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);

Route::get('/schedules/user/{user_id}', [ScheduleController::class, 'getByUserId']);
Route::get('/schedules/user/{user_id}/day/{day_of_week}', [ScheduleController::class, 'getByUserIdAndDay']);
Route::get('/schedules/user/{user_id}/weekly', [ScheduleController::class, 'getWeeklySchedule']);
Route::get('/schedules/user/{user_id}/closest', [ScheduleController::class, 'getClosestSchedule']);
Route::get('/schedules/user/{user_id}/next-available', [ScheduleController::class, 'getNextAvailableSchedule']);
Route::get('/schedules/user/{user_id}/next-dates', [ScheduleController::class, 'getNextAvailableDates']);

//rutas del historial
Route::get('/histories', [HistoryController::class, 'index']);
Route::post('/histories', [HistoryController::class, 'store']);
Route::put('/histories/{id}/update-status', [HistoryController::class, 'updateStatus']);
Route::get('/histories/user/{userId}', [HistoryController::class, 'getByUserId']);
Route::get('/histories/user/{userId}/queue', [HistoryController::class, 'getQueueByUserId']);

// Rutas para gestionar las conexiones con las plataformas de redes sociales
Route::get('/connections', [ConnectionController::class,'index']);
Route::post('/connections', [ConnectionController::class,'store']);
Route::get('/connections/user/{user_id}/platform-status', [ConnectionController::class, 'getPlatformsStatusByUserId']);
Route::delete('/connections/{id}', [ConnectionController::class, 'destroy']);

// Rutas para LinkedIn
Route::get('/connections/linkedin/authorize', [LinkedInController::class,'getLinkedInAuthorize']);
Route::post('/connections/linkedin/access-token', [LinkedInController::class,'getAccessToken']);
Route::post('/connections/linkedin/create-post', [LinkedInController::class,'createPost']);

// Rutas para Reddit
Route::get('/connections/reddit/authorize', [RedditController::class,'getRedditAuthorize']);
Route::post('/connections/reddit/access-token', [RedditController::class,'getAccessToken']);
Route::post('/connections/reddit/create-post', [RedditController::class,'createPost']);

// Rutas para Mastodon
Route::get('/connections/mastodon/authorize', [MastodonController::class,'getMastodonAuthorize']);
Route::post('/connections/mastodon/access-token', [MastodonController::class,'getAccessToken']);
Route::post('/connections/mastodon/create-post', [MastodonController::class,'createPost']);
Route::get('/connections/mastodon/account-info/{user_id}', [MastodonController::class,'getAccountInfo']);

// Rutas para el Auth
Route::post('/2fa/verify', [Google2FAController::class,'verify']);
Route::put('/users/{id}/enable-2fa', [Google2FAController::class,'enable']);
Route::get('/users/{id}/qr-2fa', [Google2FAController::class,'getQRCode']);
