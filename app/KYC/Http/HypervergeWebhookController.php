<?php

namespace App\KYC\Http;

use Symfony\Component\HttpFoundation\Response;
use App\KYC\Events\HypervergeStatusReceived;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Handles webhook callbacks from Hyperverge after user completes the onboarding link.
 */
class HypervergeWebhookController extends Controller
{
    /**
     * Handle the webhook response from Hyperverge after onboarding link is used.
     *
     * @param Request $request
     * @return Response
     */
    public function __invoke(Request $request): Response
    {
        // Log the full payload for auditing and traceability
        Log::info('[HypervergeWebhook] Payload received', [
            'body' => $request->all()
        ]);

        // Validate the payload
        $validated = $request->validate([
            'transactionId' => ['required', 'string'],
            'status' => ['required', 'in:auto_approved,user_cancelled'],
        ]);

        // Dispatch event for application logic (e.g., status updates)
        HypervergeStatusReceived::dispatch(
            $validated['transactionId'],
            $validated['status']
        );

        return response()->json(['message' => 'Webhook received.'], 200);
    }
}
