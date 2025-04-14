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
            'description' => ['required', 'string', 'max:255'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'selfie' => ['required', 'string'], // base64 selfie
        ]);

        $vendor = Vendor::findOrFail($validated['vendor_id']);
        $amount = (float) $validated['amount'];

        try {
            // 1. 🧠 Face Verification → Find user
            $user = app(FaceVerificationPipeline::class)->run($validated['selfie']);

            // 2. 💰 Check balance
            if ((float) $user->balanceFloat < $amount) {
                return response()->json([
                    'message' => 'Insufficient balance.',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }

            // 3. 💸 Transfer funds (unconfirmed then auto-confirm)
            $meta = [
                'initiated_by' => 'face_login',
                'transfer_type' => 'vendor_checkout',
                'reason' => $validated['description'],
                'reference_code' => $validated['reference_code'] ?? null,
            ];

            $transfer = $transferService->transferUnconfirmed($user, $vendor, $amount, $meta);
            $transferService->confirmTransfer($transfer);
            $transferService->finalizeTransfer($transfer);

            // 4. ✅ Return result
            return response()->json([
                'message' => 'Payment successful',
                'amount' => $amount,
                'description' => $validated['description'],
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
