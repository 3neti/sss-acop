<?php

use App\Commerce\Http\Controllers\FacePaymentController;
use App\Http\Controllers\ProfileController;
use App\KYC\Http\HypervergeWebhookController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test-image', [\App\Http\Controllers\TestController::class, 'showBase64Page'])->name('test.image');
Route::get('/webhooks/hyperverge', HypervergeWebhookController::class)
    ->name('webhooks.hyperverge');

Route::get('/commerce', [\App\Commerce\Http\Controllers\CommerceController::class, 'index'])->name('commerce.index');
Route::get('/vendor/face-payment', function () {
    return Inertia::render('Vendor/FacePaymentPage', [
        'vendorId' => 1, //auth()->user()->id, // or however you resolve vendor
        'reference_id' => 'AA537',
        'item_description' => 'X Factor',
        'amount' => 250,
        'currency' => 'PHP',
        'id_type' => 'phl_dl',
        'id_number' => 'N01-87-049586',
        'callbackUrl' => 'https://run.mocky.io/v3/826def70-ea2c-413b-98eb-1761799c552a', // or some vendor config
    ]);
})->middleware(['auth'])->name('vendor.face.payment');

Route::post('/face-payment', FacePaymentController::class)->name('face.payment');

Route::get('/face-payment/success', function () {
    return Inertia::render('Vendor/FacePaymentSuccess');
})->name('face.payment.success');

require __DIR__.'/auth.php';
