<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;

Route::group(['middleware' => ['api', 'throttle:5,1'], 'prefix' => 'v1'], function () {
    Route::post('/register', [UserController::class, 'register']);
    Route::get('/leaderboard', [UserController::class, 'leaderboard']);
});
