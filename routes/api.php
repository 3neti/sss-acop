<?php

use App\Commerce\Http\Controllers\FacePaymentController;
use App\Commerce\Http\Controllers\API\OrderController;
use App\Commerce\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/face-payment', FacePaymentController::class)
//    ->middleware(['auth:sanctum', 'web', 'vendor'])
    ->name('face.payment');

Route::post('wallet/qr-code', [WalletController::class, 'generateDepositQRCode'])->middleware(['web'])->name('wallet.qr-code');
Route::post('wallet/topup', [WalletController::class, 'topupWallet'])->name('wallet.topup');

//Route::post('/profile/token', TokenController::class)->name('profile.token.generate');

// Group for vendor-authenticated API routes
Route::middleware(['auth:sanctum', 'vendor'])->prefix('orders')->name('api.orders.')->group(function () {
    Route::post('/', [OrderController::class, 'store'])->name('store');
});
