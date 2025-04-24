<?php

namespace App\Commerce\Http\Controllers;

use App\Commerce\Exceptions\InsufficientFunds;
use App\Commerce\Exceptions\UnauthorizedVendor;
use App\KYC\Exceptions\FaceVerificationFailedException;
use App\KYC\Exceptions\FacePhotoNotFoundException;
use App\Commerce\Services\TransferFundsService;
use App\KYC\Services\FaceVerificationPipeline;
use Illuminate\Support\Facades\{Log, Storage};
use Symfony\Component\HttpFoundation\Response;
use App\Commerce\Models\{Order, Vendor};
use App\Commerce\Jobs\NotifyCallbackJob;
use FrittenKeeZ\Vouchers\Models\Voucher;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\User;
use Spatie\Url\Url;
use Exception;

class FacePaymentController extends Controller
{
    public function __invoke(Request $request, TransferFundsService $transferService)
    {
        $validated = $request->validate([
            'voucher_code' => ['required', 'string'],
            'selfie' => ['required', 'string'],
        ]);

        $voucher = Voucher::where('code', $validated['voucher_code'])->firstOrFail();
        $order = $voucher->getEntities(Order::class)->firstOrFail();
        $vendor = $voucher->owner;

        $currency = $order->currency;
        $referenceId = $order->reference_id;
        $amount = (float) $order->amount;
;
        $user = User::where([
            'id_type' => $order->meta['id_type'] ?? null,
            'id_number' => $order->meta['id_number'] ?? null,
        ])->firstOrFail();

        $meta = [
            'initiated_by' => 'face_login',
            'transfer_type' => 'vendor_checkout',
            'reason' => $order->meta['item_description'],
            'reference_id' => $referenceId,
            'currency' => $currency,
            'callback_url' => $order->callback_url ?? null,
        ];

        $transfer = null;

        try {
            $media = $user->getFirstMedia('photo');
            if (! $media) {
                throw new FacePhotoNotFoundException;
            }

            if (! $user->hasSufficientBalance($amount)) {
                throw new InsufficientFunds('Insufficient balance.');
//                return $this->respondWith($request, 'Insufficient balance.', Response::HTTP_PAYMENT_REQUIRED);
            }

            $transfer = $transferService->transferUnconfirmed($user, $vendor, $amount, $meta);

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

            $transferService->confirmTransfer($transfer);
            $transferService->finalizeTransfer($transfer);

            $callbackUrl = $order->callback_url;

            $url = Url::fromString($callbackUrl);

            return $callbackUrl
                ? inertia()->location($url->withQueryParameters(['reference_id' => $referenceId, 'status' => 'ayus'])->__toString())
                : redirect()->route('face.payment.success');

        } catch (UnauthorizedVendor $e) {
            abort(Response::HTTP_UNAUTHORIZED, $e->getMessage());
        } catch (FacePhotoNotFoundException $e) {
            abort(Response::HTTP_NOT_FOUND, $e->getMessage());
        } catch (FaceVerificationFailedException $e) {
            abort($e->getCode(), $e->getMessage());
        } catch (InsufficientFunds $e) {
            abort(Response::HTTP_PAYMENT_REQUIRED, $e->getMessage());
        } catch (Exception $e) {
            if ($transfer) {
                $transferService->rollbackTransfer($transfer);
            }

            Log::error('[FacePayment] Error occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            abort(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }

    protected function respondWith(Request $request, string|array $payload, int $status = 400)
    {
        $data = is_array($payload) ? $payload : ['message' => $payload];

        if ($request->expectsJson()) {
            return response()->json($data, $status);
        }

        return back()->withInput()->withErrors($data);
    }
}
