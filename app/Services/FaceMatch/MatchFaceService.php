<?php

namespace App\Services\FaceMatch;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MatchFaceService
{
    protected string $disk = 'public';
    protected string $endpoint = '/matchFace';

    /**
     * Perform face match using Hyperverge API.
     *
     * @param  string  $referenceCode    Unique transaction ID
     * @param  string  $base64img        Base64-encoded selfie image
     * @param  string  $storedImagePath  Absolute path to the stored image
     * @param  string|null  $type        Optional comparison type (default: face_face)
     * @return object|null               Parsed response object from Hyperverge
     *
     * @throws \Exception
     */
    public function match(string $referenceCode, string $base64img, string $storedImagePath, ?string $type = null): ?object
    {
        Log::info("[MatchFaceService] Starting face match", ['referenceCode' => $referenceCode]);

        $tempImagePath = null;

        try {
            // Decode base64 selfie and save to temp location
            $tempImagePath = $this->storeSelfie($referenceCode, $base64img);

            // Prepare headers
            $headers = [
                'appId' => config('kwyc-check.credential.appId'),
                'appKey' => config('kwyc-check.credential.appKey'),
                'transactionId' => $referenceCode,
            ];

            Log::info("[MatchFaceService] Preparing images", [
                'selfie_exists' => file_exists($tempImagePath),
                'selfie_size' => filesize($tempImagePath),
                'stored_exists' => file_exists($storedImagePath),
                'stored_size' => filesize($storedImagePath),
            ]);

            $response = Http::withHeaders($headers)
                ->attach('selfie', fopen($tempImagePath, 'r'), 'selfie.jpeg')
                ->attach('selfie2', fopen($storedImagePath, 'r'), 'stored.jpeg')
                ->attach('type', $type ?? 'face_face')
                ->post(config('kwyc-check.credential.base_url') . $this->endpoint);

            if ($response->failed()) {
                Log::error("[MatchFaceService] API request failed", ['response' => $response->body()]);
                throw new Exception("Face match request failed.");
            }

            Log::debug("[MatchFaceService] Response JSON", ['json' => $response->json()]);
            return $response->object();

        } catch (Exception $e) {
            Log::error("[MatchFaceService] Error occurred", ['exception' => $e->getMessage()]);
            throw new Exception("Face match failed: " . $e->getMessage());
        } finally {
            if ($tempImagePath && file_exists($tempImagePath)) {
                Storage::disk($this->disk)->delete($this->relativePath($tempImagePath));
                Log::info("[MatchFaceService] Cleaned up temporary selfie", ['deleted' => $tempImagePath]);
            }
        }
    }

    /**
     * Decodes base64 image and stores it in the temp path.
     * @throws Exception
     */
    protected function storeSelfie(string $referenceCode, string $base64img): string
    {
        $base64 = substr($base64img, strpos($base64img, ',') + 1);
        $decoded = base64_decode($base64);

        if (!$decoded) {
            throw new Exception("Base64 decoding failed.");
        }

        $relative = "image/{$referenceCode}.JPEG";
        Storage::disk($this->disk)->put($relative, $decoded);
        $absolute = Storage::disk($this->disk)->path($relative);

        Log::info("[MatchFaceService] Stored selfie image", ['path' => $absolute]);

        return $absolute;
    }

    /**
     * Convert absolute path to relative for disk deletion.
     */
    protected function relativePath(string $absolute): string
    {
        return str_replace(Storage::disk($this->disk)->path(''), '', $absolute);
    }
}
