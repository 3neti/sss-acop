<?php

namespace App\Services\FaceMatch;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/** @deprecated  */
class GenerateLinkService
{
    protected string $endpoint = 'https://ind.idv.hyperverge.co/v1/link-kyc/start';

    public function createLink(string $transactionId, string $redirectUrl, array $options = []): array
    {
        $payload = array_merge([
            'workflowId' => 'onboarding',
            'transactionId' => $transactionId,
            'redirectUrl' => $redirectUrl,
            'validateWorkflowInputs' => 'yes',
            'allowEmptyWorkflowInputs' => 'no',
            'forceCreateLink' => 'no',
            'redirectTime' => '5',
        ], $options);

        $headers = [
            'appId' => config('kwyc-check.credential.appId'),
            'appKey' => config('kwyc-check.credential.appKey'),
        ];

        try {
            $response = Http::withHeaders($headers)->post($this->endpoint, $payload);

            if ($response->failed()) {
                Log::error('[OnboardingService] Failed to create link', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                throw new RequestException($response);
            }

            return $response->json();
        } catch (RequestException $e) {
            throw new Exception("Onboarding link creation failed: " . $e->getMessage());
        }
    }
}
