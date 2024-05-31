<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('user/register', [AuthController::class,'createAccount']);
Route::post('user/login', [AuthController::class,'login']);
Route::post('otp_verify', [AuthController::class,'verifyOTP']);


Route::middleware(['superadmin','auth:sanctum'])->group(function () {
    Route::post('admin/create-game', [GameController::class, 'createGame']);
});


Route::middleware(['auth:sanctum'])->group(function (){
    Route::get('/users', function (Request $request){
        return $request->user();
    });

    Route::post('games/join', [GameController::class, 'joinGame']);
});

