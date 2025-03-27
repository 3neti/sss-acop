<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Exception;

class MatchFace
{
    use AsAction;

    public function handle(string $referenceCode, string $base64img, string $storedImagePath, ?string $type = null): ?object
    {
        Log::info("[MatchFace] Starting face match", ['referenceCode' => $referenceCode]);

        // Save base64 selfie
        try {
            $base64 = substr($base64img, strpos($base64img, ',') + 1);
            $decoded = base64_decode($base64);
            if (!$decoded) {
                throw new Exception("Base64 decode failed.");
            }

            $fileName = "{$referenceCode}.JPEG";
            $tempImagePath = "image/{$fileName}";

            // Ensure it's stored on the 'public' disk
            Storage::disk('public')->put($tempImagePath, $decoded);
            $selfiePath = Storage::disk('public')->path($tempImagePath);

            Log::info("[MatchFace] Saved temporary selfie", ['path' => $selfiePath]);
        } catch (Exception $e) {
            Log::error("[MatchFace] Failed to process base64 selfie", ['error' => $e->getMessage()]);
            throw new Exception("Selfie image processing failed.");
        }

        // Build and send the HTTP request
        try {
            $headers = [
                'appId' => config('kwyc-check.credential.appId'),
                'appKey' => config('kwyc-check.credential.appKey'),
                'transactionId' => $referenceCode,
            ];

            Log::info("[MatchFace] Sending request to Hyperverge");

            Log::info("[MatchFace] Preparing images", [
                'selfie_exists' => file_exists($selfiePath),
                'selfie_size' => filesize($selfiePath),
                'stored_exists' => file_exists($storedImagePath),
                'stored_size' => filesize($storedImagePath),
            ]);

            Log::debug("[MatchFace] Sent type param", ['type' => $type ?? 'face_face']);

            $response = Http::withHeaders($headers)
                ->attach('selfie', fopen($selfiePath, 'r'), 'selfie.jpeg')
                ->attach('selfie2', fopen($storedImagePath, 'r'), 'stored.jpeg');
//                ->attach('image1', fopen($selfiePath, 'r'), 'selfie.jpeg')
//                ->attach('image2', fopen($storedImagePath, 'r'), 'stored.jpeg')
//                ->attach('image1', file_get_contents($selfiePath), 'selfie.jpeg')
//                ->attach('image2', file_get_contents($storedImagePath), 'stored.jpeg')
            ;

//            if ($type) {
//                $response = $response->attach('type', $type);
//            }
            $response = $response->attach('type', $type ?? 'face_face');

            $response = $response->post(config('kwyc-check.credential.base_url') . '/matchFace');

            if ($response->failed()) {
                Log::error("[MatchFace] API request failed", ['response' => $response->body()]);
                throw new Exception("Face match request failed. Check logs.");
            }

            Log::debug("[MatchFace] Raw response JSON", ['json' => $response->json()]);

            Log::info("[MatchFace] Received response", ['body' => $response->body()]);

            return $response->object();
        } catch (Exception $e) {
            Log::error("[MatchFace] HTTP request error", ['error' => $e->getMessage()]);
            throw new Exception("Hyperverge face match API call failed.");
        } finally {
            if (isset($selfiePath) && file_exists($selfiePath)) {
                Storage::disk('public')->delete($tempImagePath);
                Log::info("[MatchFace] Cleaned up temporary selfie", ['deleted' => $tempImagePath]);
            }
        }
    }
}
