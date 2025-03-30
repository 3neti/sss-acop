<?php

namespace App\KYC\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Exception;

class FaceVerificationPipeline
{
    protected LivelinessService $liveliness;
    protected MatchFaceService $matcher;

    /**
     * Error code to friendly message map from Hyperverge.
     */
    protected array $errorCodeMap = [
        '119' => 'Liveness failed with high confidence',
        '122' => 'Face not detected in selfie image.',
        '124' => 'Eyes are closed in the selfie.',
        '433' => 'Multiple faces detected.',
        '438' => 'Face appears blurred.',
        '439' => 'Face is masked or covered.',
        '5xx' => 'Server error. Please try again.',
    ];

    public function __construct(LivelinessService $liveliness, MatchFaceService $matcher)
    {
        $this->liveliness = $liveliness;
        $this->matcher = $matcher;
    }

    /**
     * Run liveliness check and match face.
     *
     * @throws Exception with user-friendly error message
     */
    public function verify(string $referenceCode, string $base64img, string $storedImagePath): array
    {
        Log::info('[FaceVerificationPipeline] Starting pipeline');

        // Step 1: Liveliness Check
        $liveliness = app(LivelinessService::class)->verify($referenceCode, $base64img);

        $liveValue = Arr::get($liveliness, 'result.details.liveFace.value');
        $liveAction = Arr::get($liveliness, 'result.summary.action');

        if ($liveValue !== 'yes' || $liveAction !== 'pass') {
            $summaryReasons = collect(Arr::get($liveliness, 'result.summary.details', []))
                ->pluck('message')
                ->filter()
                ->toArray();

            $summaryLower = collect($summaryReasons)->map(fn ($msg) => strtolower($msg));
            $qualityReasons = collect(Arr::get($liveliness, 'result.details.qualityChecks', []))
                ->filter(fn ($check) => Arr::get($check, 'value') === 'yes')
                ->keys()
                ->map(fn ($key) => ucwords(str_replace(['_', 'Present'], [' ', ''], $key)))
                ->filter(fn ($reason) => !$summaryLower->contains(strtolower($reason)))
                ->toArray();

            $reasons = array_merge($summaryReasons, $qualityReasons);
            $reasonStr = implode('; ', $reasons) ?: 'Liveliness check failed.';
            $reasonStr = implode('; ', $reasons) ?: 'Liveliness check failed.';

            throw new Exception("Liveliness check failed: {$reasonStr}");
        }

        // Step 2: Face Match Check
        return app(MatchFaceService::class)->match(
            referenceCode: $referenceCode,
            base64img: $base64img,
            storedImagePath: $storedImagePath
        );
    }
//    public function verify(string $referenceCode, string $base64img, string $storedImagePath): array
//    {
//        // Step 1: Liveliness Check
//        $livelinessResult = $this->liveliness->verify($referenceCode, $base64img);
//
//        $action = Arr::get($livelinessResult, 'result.summary.action');
//        if ($action !== 'pass') {
//            $details = Arr::get($livelinessResult, 'result.summary.details', []);
//            $code = $details[0]['code'] ?? null;
//            $message = $this->errorCodeMap[$code] ?? ($details[0]['message'] ?? 'Liveliness check failed.');
//
//            Log::warning('[FacePipeline] Liveliness failed', compact('code', 'message'));
//            throw new Exception($message);
//        }
//
//        Log::info('[FacePipeline] Liveliness passed');
//
//        // Step 2: Face Match
//        return $this->matcher->match(
//            referenceCode: $referenceCode,
//            base64img: $base64img,
//            storedImagePath: $storedImagePath
//        );
//    }
}
