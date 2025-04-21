<?php

namespace App\Commerce\Http\Controllers;

use App\Commerce\Models\Transfer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Commerce\Actions\GenerateDepositQRCode;
use App\Commerce\Actions\TopupWallet;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
//    public function generateDepositQRCode(Request $request): \Illuminate\Http\JsonResponse
//    {
//        $validated = $request->validate([
//            'amount' => ['required', 'numeric', 'min:50'],
//            'account' => ['nullable', 'numeric', 'starts_with:0', 'max_digits:11'],
//        ]);
//
//        // Prepare amount and account values
//        $amount = $validated['amount'];
//        $account = $validated['account'] ?? 'default';
//        $user = $request->user();
//
//        // Create a unique cache key using amount and account
//        $cacheKey = "deposit_qr_{$amount}_{$account}";
//
//        logger()->info('[WalletController] QR request received', compact('amount', 'account', 'cacheKey'));
//
//        try {
//
//            // Check if the QR code is already cached, or generate and cache it for 30 minutes
//            $qrCode = cache()->remember($cacheKey, now()->addMinutes(30), function () use ($amount, $account) {
//                logger()->info('Generating new QR code for deposit', compact('amount', 'account'));
//                return GenerateDepositQRCode::run($amount, $account);
//            });
//
//            logger()->info('[WalletController] QR code ready', compact('cacheKey'));
//
//            return response()->json([
//                'success' => true,
//                'qr_code' => $qrCode,
//            ]);
//
//        } catch (\Exception $e) {
//            logger()->error('[WalletController] QR code generation failed', [
//                'cacheKey' => $cacheKey,
//                'message' => $e->getMessage(),
//            ]);
//
//            return response()->json([
//                'success' => false,
//                'message' => $e->getMessage(),
//            ], 500);
//        }
//    }

    public function generateDepositQRCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:50'],
            'account' => ['nullable', 'numeric', 'starts_with:0', 'max_digits:11'],
        ]);

        $amount = $validated['amount'];
        $account = $validated['account'] ?? 'default';
        $user = $request->user();
        $cacheKey = "deposit_qr_{$amount}_{$account}";
        $metaKey = "deposit_meta_{$cacheKey}";

        logger()->info('[WalletController] QR request received', compact('amount', 'account', 'cacheKey', 'metaKey'));

        try {
            // Store metadata separately
            Cache::put($metaKey, [
                'amount' => $amount,
                'account' => $account,
                'user_id' => $user?->id,
            ], now()->addMinutes(30));

            // Generate QR Code (only the image string is cached here)
            $qrCode = cache()->remember($cacheKey, now()->addMinutes(30), function () use ($amount, $account) {
                logger()->info('Generating new QR code for deposit', compact('amount', 'account'));
                return GenerateDepositQRCode::run($amount, $account);
            });

            logger()->info('[WalletController] QR code ready', compact('cacheKey'));

            // Simulated top-up job
            dispatch(function () use ($metaKey) {
                sleep(5); // simulate delay

                $data = Cache::get($metaKey);
                if (! $data || empty($data['user_id'])) {
                    logger()->warning('[WalletController] Skipped simulated top-up: no user_id.', compact('metaKey'));
                    return;
                }

                $user = \App\Models\User::find($data['user_id']);
                if (! $user) {
                    logger()->warning('[WalletController] Simulated top-up: user not found', ['user_id' => $data['user_id']]);
                    return;
                }

                logger()->info('[WalletController] Simulated top-up starting', ['user_id' => $user->id]);

                $transfer = TopupWallet::run(
                    user: $user,
                    amount: $data['amount'],
                    meta: [
                        'account' => $data['account'],
                        'cache_key' => $metaKey,
                        'simulated' => true,
                        'source' => 'mock',
                    ]
                );

                logger()->info('[WalletController] Simulated top-up completed', [
                    'user_id' => $user->id,
                    'amount' => $transfer->deposit->amountFloat,
                    'transfer_id' => $transfer->id,
                ]);
            })->afterResponse();

            return response()->json([
                'success' => true,
                'qr_code' => $qrCode,
            ]);

        } catch (\Exception $e) {
            logger()->error('[WalletController] QR code generation failed', [
                'cacheKey' => $cacheKey,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function topupWallet(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'cacheKey' => ['required', 'string'],
        ]);
        $cacheKey = $validated['cacheKey'];
        $user = $request->user(); // Authenticated user

        logger()->info('[WalletController] Top-up requested', [
            'user_id' => $user->id,
            'cacheKey' => $cacheKey,
        ]);

        // Retrieve amount/account from cache
        $data = Cache::get($cacheKey);

        if (! $data || ! isset($data['amount'], $data['account'])) {
            logger()->warning('[WalletController] Top-up cache missing or incomplete', compact('cacheKey'));
            throw new NotFoundHttpException('Top-up data not found. The QR code may have expired.');
        }

        try {
            logger()->info('[WalletController] Running top-up...', [
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'account' => $data['account'],
            ]);

            $transfer = TopupWallet::run(
                user: $user,
                amount: $data['amount'],
                meta: [
                    'source' => 'gcash',
                    'account' => $data['account'],
                    'cache_key' => $cacheKey,
                ]
            );

            logger()->info('[WalletController] Top-up successful', [
                'transfer_id' => $transfer->id,
                'user_id' => $user->id,
                'amount' => $transfer->amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Wallet topped up successfully.',
                'transfer' => [
                    'id' => $transfer->id,
                    'amount' => $transfer->amount,
                    'meta' => $transfer->meta,
                ],
            ]);

        } catch (\Throwable $e) {
            logger()->error('[WalletController] Top-up failed', [
                'user_id' => $user->id,
                'cacheKey' => $cacheKey,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Top-up failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
