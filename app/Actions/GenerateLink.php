<?php

namespace App\Actions;

use Illuminate\Http\Client\RequestException;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class GenerateLink
{
    use AsAction;

    protected string $endpoint = 'https://ind.idv.hyperverge.co/v1/link-kyc/start';

    /**
     * Main handle method returns full Hyperverge response.
     *
     * @param string $transactionId
     * @param string|null $redirectUrl
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function handle(string $transactionId, ?string $redirectUrl = null, array $options = []): array
    {
        $payload = array_merge([
            'workflowId' => 'onboarding',
            'transactionId' => $transactionId,
            'redirectUrl' => $redirectUrl ?? route('webhooks.hyperverge'),
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
                Log::error('[GenerateLink] Failed to generate onboarding link', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RequestException($response);
            }

            $json = $response->json();

            Log::info('[GenerateLink] Onboarding link created successfully', [
                'transactionId' => $transactionId,
                'startKycUrl' => $json['result']['startKycUrl'] ?? null,
            ]);

            return $json;
        } catch (RequestException $e) {
            throw new Exception("Hyperverge onboarding link creation failed: " . $e->getMessage());
        }
    }

    /**
     * Shortcut method to return only the KYC link as a string.
     *
     * @param string $transactionId
     * @param string|null $redirectUrl
     * @param array $options
     * @return string
     * @throws \Exception
     */
    public static function get(string $transactionId, ?string $redirectUrl = null, array $options = []): string
    {
        $response = static::run($transactionId, $redirectUrl, $options);
        $url = $response['result']['startKycUrl'] ?? null;

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid KYC link URL received from Hyperverge.');
        }

        return $url;
    }
}
