<?php

use App\Commerce\Http\Controllers\FacePaymentController;
use App\Commerce\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/face-payment', FacePaymentController::class)
//    ->middleware(['auth'])
    ->middleware(['auth:sanctum', 'web', 'vendor'])
//    ->middleware(['auth:sanctum', 'vendor'])
    ->name('face.payment');

Route::post('wallet/qr-code', [WalletController::class, 'generateDepositQRCode'])->middleware(['web'])->name('wallet.qr-code');
Route::post('wallet/topup', [WalletController::class, 'topupWallet'])->name('wallet.topup');
