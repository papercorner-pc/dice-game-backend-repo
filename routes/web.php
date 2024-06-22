<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('user/save-fcm-token', [\App\Http\Controllers\Api\StreamController::class,'saveFcmKey'])->name('store.fcm_token');
