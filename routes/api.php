<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SmsController;
use App\Http\Controllers\Api\WalletManageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('user/register', [AuthController::class,'createAccount']);
Route::post('user/login', [AuthController::class,'login']);
Route::post('otp_verify', [AuthController::class,'verifyOTP']);
Route::post('/send-sms', [SmsController::class, 'sendSms']);

Route::get('/login', function() {
    return response()->json(['message' => 'Please log in'], 401);
})->name('login');

Route::middleware(['superadmin','auth:sanctum'])->group(function () {
    Route::post('admin/create-game', [GameController::class, 'createGame']);
    Route::post('result/announce', [GameController::class, 'announceResult']);
    Route::post('admin/delete-game', [GameController::class, 'deleteGame']);
    Route::post('admin/edit-game', [GameController::class, 'editGame']);
    Route::post('agent/wallet-status-update', [WalletManageController::class, 'agentRequestStatusUpdate']);
    Route::post('admin/wallet-recharge', [WalletManageController::class, 'adminWalletRecharge']);

});

Route::middleware(['auth:sanctum'])->group(function (){
    Route::get('/users', function (Request $request){
        return $request->user();
    });
    Route::get('/get/user-list', [UserController::class, 'getUserList']);
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
    Route::post('single/game-list',[GameController::class, 'singleGameDetail']);
    Route::post('update/profile-image', [UserController::class, 'updateProfile']);
    Route::post('game/publish-status', [GameController::class, 'gamePublishStatus']);
    Route::post('create-users', [UserController::class, 'createUser']);

    Route::post('agent/wallet-recharge', [WalletManageController::class, 'agentWalletRecharge']);
    Route::post('agent/wallet-request', [WalletManageController::class, 'agentWalletRequestAdmin']);

    Route::post('dealer/wallet-request', [WalletManageController::class, 'dealerWalletRequest']);
    Route::post('dealer/wallet-status-update', [WalletManageController::class, 'dealerRequestStatusUpdate']);
    Route::get('wallet-requests', [WalletManageController::class, 'walletRequestList']);
    Route::post('user/change-password', [UserController::class, 'changeUserPassword']);

    Route::post('game/card-balance', [GameController::class, 'getGameCardBalance']);
    Route::post('game/joined-users', [GameController::class, 'userGameList']);
    Route::post('game/delete-join', [GameController::class, 'deleteUserGameJoin']);
});


