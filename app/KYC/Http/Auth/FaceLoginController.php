<?php

namespace App\KYC\Http\Auth;

use Illuminate\Support\Facades\{Auth, Log, Storage};
use App\KYC\Services\FaceVerificationPipeline;
use App\Http\Requests\Auth\FaceLoginRequest;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;
use App\KYC\Enums\KYCIdType;
use Inertia\ResponseFactory;
use Illuminate\Support\Arr;
use App\Models\User;
use Exception;

class FaceLoginController extends Controller
{
    protected array $fields = ['id_value', 'id_type'];

    public function showForm(): InertiaResponse|ResponseFactory
    {
        return inertia('Auth/FaceLogin', [
            'fields' => $this->fields,
            'idTypes' => KYCIdType::options(),
            'autoFaceLogin' => config('sss-acop.auto_face_login'),
        ]);
    }

    public function authenticate(FaceLoginRequest $request): RedirectResponse
    {
        Log::info('[FaceLogin] Authenticating', ['fields' => $this->fields]);

        $user = $this->findUserFromRequest($request);

        if (!$user) {
            return back()->withErrors(['base64img' => 'User not found or invalid identifier.']);
        }

        $media = $user->getFirstMedia('photo');
        if (!$media) {
            return back()->withErrors(['base64img' => 'No profile image found.']);
        }

        try {
            $storedImagePath = Storage::disk($media->disk)->path($media->getPathRelativeToRoot());
            $referenceCode = uniqid('face_', true);

            $pipeline = app(FaceVerificationPipeline::class);

            $result = $pipeline->verify(
                referenceCode: $referenceCode,
                base64img: $request->base64img,
                storedImagePath: $storedImagePath
            );

            // Continue with match evaluation...
            $match = Arr::get($result, 'result.details.match.value');
            $confidence = Arr::get($result, 'result.details.match.confidence');
            $action = Arr::get($result, 'result.summary.action');

            $confidenceMap = [
                'very_high' => 95,
                'high'      => 85,
                'medium'    => 60,
                'low'       => 30,
            ];

            $confidenceScore = $confidenceMap[strtolower($confidence)] ?? 0;

            if ($match === 'yes' && $action === 'pass' && $confidenceScore >= 85) {
                Auth::login($user);
                $request->session()->regenerate();
                return redirect()->intended(route('dashboard'));
            }

            Log::warning('[FaceLogin] Verification failed', compact('match', 'confidence'));
            $summaryDetails = Arr::get($result, 'result.summary.details', []);
            $reasons = collect($summaryDetails)
                ->pluck('message')
                ->filter()
                ->implode('; ');

            $errorMessage = "Face verification failed.";
            if ($reasons) {
                $errorMessage .= " Reason: {$reasons}";
            }

            return back()->withErrors([
                'base64img' => $errorMessage,
            ]);

        } catch (Exception $e) {
            Log::error('[FaceLogin] Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Try to extract Hyperverge error message if available
            $pattern = '/{.+}/s'; // match JSON from message
            if (preg_match($pattern, $e->getMessage(), $matches)) {
                $json = json_decode($matches[0], true);
                $details = data_get($json, 'result.summary.details', []);
                $reason = collect($details)->pluck('message')->implode('; ');

                if ($reason) {
                    return back()->withErrors([
                        'base64img' => "Face login failed. {$reason}",
                    ]);
                }
            }

            return back()->withErrors([
                'base64img' => 'Face login failed. ' . $e->getMessage(),
            ]);
        }
    }

    protected function findUserFromRequest(FaceLoginRequest $request): ?User
    {
        $query = User::query();
        $conditions = [];

        foreach ($this->fields as $field) {
            if ($request->filled($field)) {
                $conditions[$field] = $request->input($field);
            }
        }

        if (!empty($conditions)) {
            Log::info('[FaceLogin] Dynamic identifier match', $conditions);
            return $query->where($conditions)->first();
        }
        Log::warning('[FaceLogin] No valid dynamic identifiers provided.');

        return null;
    }
}
