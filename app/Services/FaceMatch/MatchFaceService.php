<?php

namespace App\Services\FaceMatch;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MatchFaceService
{
    protected string $disk = 'public';
    protected string $endpoint = '/matchFace';

    /**
     * Perform a face match using the Hyperverge Face Match API.
     *
     * @param string $referenceCode     Unique transaction ID
     * @param string $base64img         Base64-encoded selfie (data URI)
     * @param string $storedImagePath   Absolute path to stored user image
     * @param string|null $type         Optional type (default: "face_face")
     * @return array                    Parsed response array
     *
     * @throws \Exception
     */
    public function match(string $referenceCode, string $base64img, string $storedImagePath, ?string $type = 'face_face'): array
    {
        Log::info("[MatchFaceService] Initiating face match", compact('referenceCode'));

        $tempImagePath = null;

        try {
            // 1. Save base64-encoded selfie image temporarily
            $tempImagePath = $this->storeSelfie($referenceCode, $base64img);

            // 2. Validate both files exist
            if (!file_exists($storedImagePath) || !file_exists($tempImagePath)) {
                throw new Exception("One or more required images are missing.");
            }

            // 3. Prepare API headers
            $headers = [
                'appId' => config('kwyc-check.credential.appId'),
                'appKey' => config('kwyc-check.credential.appKey'),
                'transactionId' => $referenceCode,
            ];

            // 4. Perform API call
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->attach('selfie', fopen($tempImagePath, 'r'), 'selfie.jpeg')
                ->attach('selfie2', fopen($storedImagePath, 'r'), 'stored.jpeg')
                ->attach('type', $type)
                ->post(config('kwyc-check.credential.base_url') . $this->endpoint);

            if ($response->failed()) {
                Log::error("[MatchFaceService] API failed", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new RequestException($response);
            }

            $json = $response->json();

            Log::info("[MatchFaceService] Face match successful", [
                'summary' => $json['result']['summary'] ?? null,
                'match' => $json['result']['details']['match'] ?? null,
            ]);

            return $json;

        } catch (RequestException $e) {
            Log::error("[MatchFaceService] HTTP error", ['message' => $e->getMessage()]);
            throw new Exception("HTTP request failed: " . $e->getMessage());

        } catch (Exception $e) {
            Log::error("[MatchFaceService] Unexpected error", ['exception' => $e]);
            throw new Exception("Face match failed: " . $e->getMessage());

        } finally {
            if ($tempImagePath && file_exists($tempImagePath)) {
                Storage::disk($this->disk)->delete($this->relativePath($tempImagePath));
                Log::info("[MatchFaceService] Temp selfie cleaned", ['deleted' => $tempImagePath]);
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

        $relativePath = "image/{$referenceCode}.JPEG";
        Storage::disk($this->disk)->put($relativePath, $decoded);

        $absolutePath = Storage::disk($this->disk)->path($relativePath);

        Log::info("[MatchFaceService] Stored selfie image", ['path' => $absolutePath]);

        return $absolutePath;
    }

    protected function relativePath(string $absolute): string
    {
        return str_replace(Storage::disk($this->disk)->path(''), '', $absolute);
    }
}
