<?php

use App\Http\Controllers\HistoryController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PostController;
use App\Http\Controllers\networks\LinkedInController;
use App\Http\Controllers\networks\RedditController;

Route::get('/user', function () {
    return "Entra";
});

Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/register', [RegisterController::class, 'store']);

// RUTA PARA VER Y GUARDAR LOS POSTS
Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class,'store']);

//rutas del historial
Route::get('/histories', [HistoryController::class, 'index']);
Route::post('/histories', [HistoryController::class, 'store']);
Route::put('/histories/{id}/update-status', [HistoryController::class, 'updateStatus']);
Route::get('/histories/user/{userId}', [HistoryController::class, 'getByUserId']);
Route::get('/histories/user/{userId}/queue', [HistoryController::class, 'getQueueByUserId']);

// Rutas para LinkedIn
Route::get('/connections/linkedin/authorize', [LinkedInController::class,'getLinkedInAuthorize']);
Route::post('/connections/linkedin/access-token', [LinkedInController::class,'getAccessToken']);
Route::post('/connections/linkedin/create-post', [LinkedInController::class,'createPost']);

// Rutas para Reddit
Route::get('/connections/reddit/authorize', [RedditController::class,'getRedditAuthorize']);
Route::post('/connections/reddit/access-token', [RedditController::class,'getAccessToken']);
Route::post('/connections/reddit/create-post', [RedditController::class,'createPost']);
