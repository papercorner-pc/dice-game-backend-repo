<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('user/register', [AuthController::class,'createAccount']);
Route::post('user/login', [AuthController::class,'login']);
Route::post('otp_verify', [AuthController::class,'verifyOTP']);

Route::get('/login', function() {
    return response()->json(['message' => 'Please log in'], 401);
})->name('login');

Route::middleware(['superadmin','auth:sanctum'])->group(function () {
    Route::post('admin/create-game', [GameController::class, 'createGame']);
    Route::post('game/joined-users', [GameController::class, 'userGameList']);

});


Route::middleware(['auth:sanctum'])->group(function (){
    Route::get('/users', function (Request $request){
        return $request->user();
    });

    Route::post('games/join', [GameController::class, 'joinGame']);
    Route::post('game/list', [GameController::class, 'gameList']);
    Route::post('game/detail', [GameController::class, 'gameDetail']);

    Route::post('/user-wallet/recharge', [UserController::class, 'rechargeUserWallet']);
    Route::post('/user-wallet/debit', [UserController::class, 'debitUserWallet']);
    Route::get('/user-wallet/history', [UserController::class, 'walletHistory']);
    Route::post('/user-logout', [AuthController::class, 'logout']);

    Route::get('user/profile',[UserController::class, 'userProfile']);
    Route::post('user/change-password', [UserController::class, 'changePassword']);

    Route::get('games/search', [GameController::class, 'searchGames']);
    Route::get('games/filter', [GameController::class, 'filterGames']);

    Route::post('user/edit-profile', [UserController::class, 'editProfile']);
});

