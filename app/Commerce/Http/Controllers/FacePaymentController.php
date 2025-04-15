<?php

namespace App\Commerce\Http\Controllers;

use App\KYC\Exceptions\FaceVerificationFailedException;
use App\KYC\Exceptions\FacePhotoNotFoundException;
use App\Commerce\Services\TransferFundsService;
use App\KYC\Services\FaceVerificationPipeline;
use Illuminate\Support\Facades\{Log, Storage};
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Controller;
use App\Commerce\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\User;
use Exception;

class FacePaymentController extends Controller
{
    protected array $fields;

    public function __construct()
    {
        $this->fields = config('sss-acop.identifiers', ['id_number', 'id_type']);
    }

    public function __invoke(Request $request, TransferFundsService $transferService)
    {
        $rules = [
            'vendor_id' => ['required', 'exists:vendors,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'item_description' => ['required', 'string', 'max:255'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'callback_url' => ['nullable', 'url'],
            'selfie' => ['required', 'string'],
        ];

        foreach ($this->fields as $field) {
            $rules[$field] = config("sss-acop.field_rules.$field", ['required', 'string']);
        }

        $validated = $request->validate($rules);

        $vendor = Vendor::findOrFail($validated['vendor_id']);
        $currency = $validated['currency'] ?? 'PHP';
        $referenceId = $validated['reference_id'] ?? uniqid('face_', true);
        $amount = (float) $validated['amount'];

        $user = $this->findUser($validated);
        if (! $user) {
            return response()->json(['message' => 'User not found.'], Response::HTTP_NOT_FOUND);
        }

        $meta = [
            'initiated_by' => 'face_login',
            'transfer_type' => 'vendor_checkout',
            'reason' => $validated['item_description'],
            'reference_id' => $referenceId,
            'currency' => $currency,
            'callback_url' => $validated['callback_url'] ?? null,
        ];

        $transfer = null;
        try {

            $media = $user->getFirstMedia('photo');
            if (! $media) {
                throw new FacePhotoNotFoundException;
            }

            // Step 1: Ensure sufficient funds
            if (! $user->hasSufficientBalance($amount)) {
                return response()->json(['message' => 'Insufficient balance.'], Response::HTTP_PAYMENT_REQUIRED);
            }

            // Step 2: Reserve transfer (unconfirmed)
            $transfer = $transferService->transferUnconfirmed($user, $vendor, $amount, $meta);

            // Step 3: Face verification
            $storedImagePath = Storage::disk($media->disk)->path($media->getPathRelativeToRoot());
            $result = app(FaceVerificationPipeline::class)->verify(
                referenceCode: $referenceId,
                base64img: $validated['selfie'],
                storedImagePath: $storedImagePath
            );
            $match = Arr::get($result, 'result.details.match.value');
            $confidence = Arr::get($result, 'result.details.match.confidence');
            $action = Arr::get($result, 'result.summary.action');

            $confidenceMap = [
                'very_high' => 95,
                'high' => 85,
                'medium' => 60,
                'low' => 30,
            ];

            $confidenceScore = $confidenceMap[strtolower($confidence)] ?? 0;

            if ($match !== 'yes' || $action !== 'pass' || $confidenceScore < 85) {
                $transferService->abortUnconfirmedTransfer($transfer, 'face_mismatch');
                throw new FaceVerificationFailedException(
                    reason: Arr::get($result, 'result.summary.details.0.message') ?? 'Unmatched face.'
                );
            }

            // Step 4: Confirm transfer (commit balance changes)
            $transferService->confirmTransfer($transfer);

            Log::info('[Debug] User Balance: ' . $user->fresh()->balanceFloat);
            Log::info('[Debug] Vendor Balance: ' . $vendor->fresh()->balanceFloat);

            // Step 5 (optional): Finalize later (can be queued)
            $transferService->finalizeTransfer($transfer);

            return response()->json([
                'message' => 'Payment successful',
                'amount' => $amount,
                'currency' => $currency,
                'item_description' => $validated['item_description'],
                'reference_id' => $referenceId,
                'callback_url' => $validated['callback_url'] ?? null,
                'user_id' => $user->id,
                'transfer_uuid' => $transfer->uuid,
            ]);
        } catch (FacePhotoNotFoundException $e) {
            return response()->json([
                'message' => 'No stored photo found for user.',
            ], Response::HTTP_NOT_FOUND);
        } catch (FaceVerificationFailedException $e) {
            return response()->json($e->toArray(), $e->getCode());
        } catch (Exception $e) {
            if ($transfer) {
                $transferService->rollbackTransfer($transfer);
            }

            Log::error('[FacePayment] Error occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Unable to complete payment.',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    protected function findUser(array $validated): ?User
    {
        $conditions = Arr::only($validated, $this->fields);

        if (! empty($conditions)) {
            Log::info('[FacePayment] Resolving user', $conditions);
            return User::where($conditions)->first();
        }

        Log::warning('[FacePayment] No valid user identifier provided');
        return null;
    }
}
