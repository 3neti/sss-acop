<?php

namespace App\KYC\Services;

use Illuminate\Support\Facades\{Http, Log, Storage};
use Illuminate\Http\Client\RequestException;
use Exception;

class LivelinessService
{
    protected string $disk = 'public';
    protected string $endpoint = '/checkLiveness';

    /**
     * Check the liveliness of a selfie image.
     *
     * @param string $referenceCode    Transaction ID for tracking
     * @param string $base64img        Base64-encoded selfie image (data URI format)
     * @return array                   Parsed response from Hyperverge
     *
     * @throws Exception
     */
    public function verify(string $referenceCode, string $base64img): array
    {
        Log::info('[LivelinessService] Verifying liveliness', compact('referenceCode'));

        $tempImagePath = null;

        try {
            $tempImagePath = $this->storeSelfie($referenceCode, $base64img);

            $headers = [
                'appId' => config('kwyc-check.credential.appId'),
                'appKey' => config('kwyc-check.credential.appKey'),
                'transactionId' => $referenceCode,
            ];

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->attach('image', fopen($tempImagePath, 'r'), 'selfie.jpeg')
                ->post(config('kwyc-check.credential.base_url') . $this->endpoint);

            if ($response->failed()) {
                Log::error("[LivelinessService] API failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $json = $response->json();

                // Try to extract the friendly error message
                $summary = $json['result']['summary']['details'] ?? [];
                $friendly = collect($summary)->pluck('message')->implode('; ');

                if ($friendly) {
                    throw new Exception("Liveliness check failed: {$friendly}");
                }

                // fallback
                throw new RequestException($response);
            }

            $json = $response->json();
            Log::info('[LivelinessService] Liveliness response received', [
                'summary' => $json['result']['summary'] ?? null,
                'liveFace' => $json['result']['details']['liveFace'] ?? null,
            ]);

            return $json;
        } catch (RequestException $e) {
            Log::error('[LivelinessService] HTTP request failed', ['message' => $e->getMessage()]);
            throw new Exception("Liveliness check failed: " . $e->getMessage());
        } catch (Exception $e) {
            Log::error('[LivelinessService] General error', ['exception' => $e]);
            throw new Exception("Liveliness check failed: " . $e->getMessage());
        } finally {
            if ($tempImagePath && file_exists($tempImagePath)) {
                Storage::disk($this->disk)->delete($this->relativePath($tempImagePath));
                Log::info("[LivelinessService] Deleted temp selfie", ['path' => $tempImagePath]);
            }
        }
    }

    protected function storeSelfie(string $referenceCode, string $base64img): string
    {
        $base64 = substr($base64img, strpos($base64img, ',') + 1);
        $decoded = base64_decode($base64);

        if (!$decoded) {
            throw new Exception("Base64 decode failed.");
        }

        $relative = "image/{$referenceCode}.JPEG";
        Storage::disk($this->disk)->put($relative, $decoded);

        $absolute = Storage::disk($this->disk)->path($relative);
        Log::info('[LivelinessService] Stored selfie image', ['path' => $absolute]);

        return $absolute;
    }

    protected function relativePath(string $absolute): string
    {
        return str_replace(Storage::disk($this->disk)->path(''), '', $absolute);
    }
}
