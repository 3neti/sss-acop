<?php

use App\Commerce\Http\Controllers\FacePaymentController;
use App\Commerce\Models\Order;
use App\Commerce\Models\Vendor;
use App\KYC\Http\CompleteOnboardingController;
use App\KYC\Http\HypervergeWebhookController;
use App\KYC\Http\Auth\FaceOnboardController;
use App\Http\Controllers\ProfileController;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;


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
})->middleware(['auth', 'verified', 'email'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/test-image', [\App\Http\Controllers\TestController::class, 'showBase64Page'])->name('test.image');
Route::get('/webhooks/hyperverge', HypervergeWebhookController::class)
    ->name('webhooks.hyperverge');

Route::get('/commerce', [\App\Commerce\Http\Controllers\CommerceController::class, 'index'])->name('commerce.index');

Route::get('/vendor/face-payment/{voucher_code}', function (string $voucher_code) {
    $voucher = Voucher::where('code', $voucher_code)->first();

    if (! $voucher) {
        throw new NotFoundHttpException('Voucher not found.');
    }

    $order = $voucher->getEntities(Order::class)->first();

    if (! $order) {
        throw new NotFoundHttpException('Associated order not found.');
    }

    // Optional vendor check
    $vendor = $voucher->owner;

//    if (Auth::check() && (Auth::user() instanceof Vendor)) && ! $voucher->owner->is(Auth::user() {
//        throw new AccessDeniedHttpException('Unauthorized access to this voucher.');
//    }

    return Inertia::render('Vendor/FacePaymentPage', [
        'voucher_code' => $voucher_code,
        'reference_id' => $order->reference_id,
        'item_description' => $order->meta['item_description'] ?? '',
        'amount' => $order->amount,
        'currency' => $order->currency,
        'id_type' => $order->meta['id_type'] ?? '',
        'id_number' => $order->meta['id_number'] ?? '',
        'callbackUrl' => $order->callback_url,
    ]);
})->name('vendor.face.payment');

//Route::post('/face-payment', FacePaymentController::class)
//    ->middleware(['auth:sanctum', 'web', 'vendor'])
//    ->name('face.payment');

Route::get('/onboard', function () {
    return Inertia::render(
        'Vendor/Onboard'
    );
})->name('onboard');

Route::post('/face-onboard', FaceOnboardController::class )->name('face.onboard');

Route::get('/face-payment/success', function () {
    return Inertia::render('Vendor/FacePaymentSuccess');
})->name('face.payment.success');

// Page that shows onboarding status (polls for completion)
Route::get('/onboarding-status/{transactionId}', function ($transactionId) {
    return Inertia::render('Vendor/OnboardingStatus', [
        'transactionId' => $transactionId,
    ]);
})->name('onboarding.status');

// API endpoint polled by the Vue page
Route::get('/onboarding-status/{transactionId}/check', function ($transactionId) {
    return response()->json([
        'status' => cache()->get("kyc_status_{$transactionId}") ?? 'pending',
    ]);
})->name('onboarding.status.check');

Route::get('/onboarding/redirect/{transactionId}', CompleteOnboardingController::class)
    ->name('onboarding.redirect');

require __DIR__.'/auth.php';
