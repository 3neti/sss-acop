<?php

namespace App\Commerce\Http\Controllers;

use App\Commerce\Services\TransferFundsService;
use App\KYC\Services\FaceVerificationPipeline;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Commerce\Models\Vendor;
use Illuminate\Http\Request;

class FacePaymentController extends Controller
{
    public function __invoke(Request $request, TransferFundsService $transferService)
    {
        $validated = $request->validate([
            'vendor_id' => ['required', 'exists:vendors,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'item_description' => ['required', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'], // e.g. PHP
            'callback_url' => ['nullable', 'url'],
            'selfie' => ['required', 'string'],
        ]);

        $vendor = Vendor::findOrFail($validated['vendor_id']);
        $amount = (float) $validated['amount'];
        $currency = $validated['currency'] ?? 'PHP';

        try {
            // 🧠 Step 1: Face verification
            $user = app(FaceVerificationPipeline::class)->run($validated['selfie']);

            // 💰 Step 2: Check balance
            if ((float) $user->balanceFloat < $amount) {
                return response()->json([
                    'message' => 'Insufficient balance.',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }

            // 💸 Step 3: Transfer
            $meta = [
                'initiated_by' => 'face_login',
                'transfer_type' => 'vendor_checkout',
                'reason' => $validated['item_description'],
                'reference_id' => $validated['reference_id'] ?? null,
                'currency' => $currency,
                'callback_url' => $validated['callback_url'] ?? null,
            ];

            $transfer = $transferService->transferUnconfirmed($user, $vendor, $amount, $meta);
            $transferService->confirmTransfer($transfer);
            $transferService->finalizeTransfer($transfer);

            // ✅ Step 4: Response
            return response()->json([
                'message' => 'Payment successful',
                'amount' => $amount,
                'currency' => $currency,
                'item_description' => $validated['item_description'],
                'reference_id' => $validated['reference_id'] ?? null,
                'callback_url' => $validated['callback_url'] ?? null,
                'user_id' => $user->id,
                'transfer_uuid' => $transfer->uuid,
            ]);
        } catch (\Throwable $e) {
            Log::error('Face payment failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Unable to complete payment.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
