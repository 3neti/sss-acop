<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\FaceLoginRequest;
use App\Services\FaceMatch\MatchFaceService;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Inertia\ResponseFactory;
use Illuminate\Support\Arr;
use App\Models\User;
use Exception;

class FaceLoginController extends Controller
{
    protected array $fields = ['user_id'];

    protected array $identifiers = [
        'email'    => 'email',
        'mobile'   => 'mobile',
        'user_id'  => 'id',
    ];

    public function showForm(): InertiaResponse|ResponseFactory
    {
        return inertia('Auth/FaceLogin', [
            'fields' => $this->fields,
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

        $media = $user->getFirstMedia('profile');
        if (!$media) {
            return back()->withErrors(['base64img' => 'No profile image found.']);
        }

        $storedImagePath = Storage::disk($media->disk)->path($media->getPathRelativeToRoot());
        $referenceCode = uniqid('face_', true);

        try {
            $matcher = app(MatchFaceService::class);

            $result = $matcher->match(
                referenceCode: $referenceCode,
                base64img: $request->base64img,
                storedImagePath: $storedImagePath,
                type: $request->type ?? 'face_face'
            );

            Log::debug('[FaceLogin] Face match response', ['result' => $result]);

            $match      = Arr::get($result, 'result.details.match.value');
            $confidence = Arr::get($result, 'result.details.match.confidence');
            $action     = Arr::get($result, 'result.summary.action');

            $confidenceMap = [
                'very_high' => 95,
                'high'      => 85,
                'medium'    => 60,
                'low'       => 30,
            ];

            $confidenceScore = $confidenceMap[strtolower($confidence)] ?? 0;

            Log::info('[FaceLogin] Face evaluation', compact('match', 'confidence', 'confidenceScore', 'action'));

            if ($match === 'yes' && $action === 'pass' && $confidenceScore >= 85) {
                Auth::login($user);
                $request->session()->regenerate();

                Log::info('[FaceLogin] Login successful', ['user_id' => $user->id]);
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

            return back()->withErrors([
                'base64img' => 'Face login failed. Please try again or contact support.',
            ]);
        }
    }

    protected function findUserFromRequest(FaceLoginRequest $request): ?User
    {
        $query = User::query();

        foreach ($this->identifiers as $input => $column) {
            if ($request->filled($input)) {
                Log::info('[FaceLogin] Identifier match', ['input' => $input, 'value' => $request->input($input)]);
                return $query->where($column, $request->input($input))->first();
            }
        }

        Log::warning('[FaceLogin] No valid identifier provided.');
        return null;
    }
}
