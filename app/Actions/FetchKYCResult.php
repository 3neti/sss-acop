<?php

namespace App\Actions;

use Psr\SimpleCache\InvalidArgumentException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\{Http, Log};
use Lorisleiva\Actions\Concerns\AsAction;
use App\Events\KYCResultFetched;
use App\Events\KYCResultFailed;
use App\Data\KYCResultData;
use Exception;

class FetchKYCResult
{
    use AsAction;

    protected string $endpoint = 'https://ind.idv.hyperverge.co/v1/link-kyc/results';
    protected string $cachePrefix = 'kyc_result';
    protected string $tag = 'kyc_results';

    /**
     * Fetch and cache KYC results using context().
     *
     * @param string $transactionId
     * @param int|null $ttl in minutes
     * @return KYCResultData
     * @throws Exception|InvalidArgumentException
     */
    public function handle(string $transactionId, ?int $ttl = null): KYCResultData
    {
        $cacheTtl = $ttl ?? config('sss-acop.result_cache_ttl', 30);

        return cache_context($this->cachePrefix)
            ->tag($this->tag)
            ->ttl($cacheTtl)
            ->remember($transactionId, function () use ($transactionId) {
                $headers = [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'appId' => config('kwyc-check.credential.appId'),
                    'appKey' => config('kwyc-check.credential.appKey'),
                ];

                $payload = ['transactionId' => $transactionId];

                try {
                    $response = Http::withHeaders($headers)
                        ->timeout(15)
                        ->retry(2, 200)
                        ->post($this->endpoint, $payload);

                    if ($response->failed()) {
                        Log::error('[FetchKYCResult] API request failed', [
                            'transactionId' => $transactionId,
                            'status' => $response->status(),
                            'body' => $response->body(),
                        ]);

                        event(new KYCResultFailed($transactionId, $response->json()));

                        throw new RequestException($response);
                    }

                    $json = $response->json();
                    $result = KYCResultData::from($json);

                    event(new KYCResultFetched($transactionId, $result));

                    Log::info('[FetchKYCResult] KYC result fetched and cached via context()', [
                        'transactionId' => $transactionId,
                        'applicationStatus' => $result->result->applicationStatus,
                    ]);

                    return $result;

                } catch (RequestException $e) {
                    Log::critical('[FetchKYCResult] RequestException', [
                        'transactionId' => $transactionId,
                        'message' => $e->getMessage(),
                    ]);

                    event(new KYCResultFailed($transactionId, ['exception' => $e->getMessage()]));

                    throw new Exception("Failed to fetch KYC result: " . $e->getMessage(), previous: $e);

                } catch (\Throwable $e) {
                    Log::critical('[FetchKYCResult] Unexpected Error', [
                        'transactionId' => $transactionId,
                        'message' => $e->getMessage(),
                    ]);

                    event(new KYCResultFailed($transactionId, ['exception' => $e->getMessage()]));

                    throw new Exception("Unexpected error while fetching KYC result.", previous: $e);
                }
            });
    }

    /**
     * Static shortcut to get the applicationStatus string.
     */
    public static function get(string $transactionId): string
    {
        return static::run($transactionId)->result->applicationStatus;
    }
}
