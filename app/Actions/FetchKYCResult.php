<?php

namespace App\Actions;

use Illuminate\Support\Facades\{Http, Log};
use Illuminate\Http\Client\RequestException;
use Psr\SimpleCache\InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Support\ParsedKYCResult;
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
     * Fetch and parse KYC results from Hyperverge.
     *
     * @param string $transactionId
     * @param int|null $ttl
     * @return ParsedKYCResult}
     * @throws Exception|InvalidArgumentException
     */
    public function handle(string $transactionId, ?int $ttl = null): ParsedKYCResult
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
                    $kyc = KYCResultData::from($json);
                    $idCardModule = ExtractIdCardValidationModule::run($kyc);
                    $selfieModule = ExtractSelfieValidationModule::run($kyc);

                    event(new KYCResultFetched($transactionId, $kyc));

                    Log::info('[FetchKYCResult] KYC result fetched & parsed', [
                        'transactionId' => $transactionId,
                        'applicationStatus' => $kyc->result->applicationStatus,
                        'idCardParsed' => filled($idCardModule),
                    ]);

                    return new ParsedKYCResult($kyc, $idCardModule, $selfieModule);

//                    return [
//                        'kyc' => $kyc,
//                        'idCardModule' => $idCardModule,
//                    ];

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
     * Shortcut to return only the application status.
     */
    public static function get(string $transactionId): string
    {
        return static::run($transactionId)['kyc']->result->applicationStatus;
    }
}
