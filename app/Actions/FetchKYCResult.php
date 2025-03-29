<?php

namespace App\Actions;

use Illuminate\Http\Client\RequestException;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Data\KYCResultData;
use Exception;

class FetchKYCResult
{
    use AsAction;

    protected string $endpoint = 'https://ind.idv.hyperverge.co/v1/link-kyc/results';

    /**
     * Fetch the KYC results using a transaction ID.
     *
     * @param string $transactionId
     * @return KYCResultData
     * @throws Exception
     */
    public function handle(string $transactionId): KYCResultData
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'appId' => config('kwyc-check.credential.appId'),
            'appKey' => config('kwyc-check.credential.appKey'),
            'transactionId' => $transactionId
        ];

        $payload = ['transactionId' => $transactionId];

        try {
            $response = Http::withHeaders($headers)->post($this->endpoint, $payload);

            if ($response->failed()) {
                Log::error('[FetchKycResult] API call failed', [
                    'transactionId' => $transactionId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RequestException($response);
            }

            $json = $response->json();

            Log::info('[FetchKycResult] Result fetched successfully', [
                'transactionId' => $transactionId,
                'applicationStatus' => $json['result']['applicationStatus'] ?? null,
            ]);

            return KYCResultData::from($json);
        } catch (RequestException $e) {
            throw new Exception("Failed to fetch KYC results from Hyperverge: " . $e->getMessage());
        }
    }
}
