<?php

namespace App\KYC\Http;

use Illuminate\Support\Facades\Redirect;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\User;

class CompleteOnboardingController extends Controller
{
    public function __invoke(string $transactionId)
    {
        // Use your own logic to resolve user from transactionId
        $user = $this->resolveUserFromKYC($transactionId);

        if (! $user) {
            return redirect()->route('onboarding.status', $transactionId)
                ->withErrors(['message' => 'Unable to complete onboarding.']);
        }

        Auth::login($user);

        Log::info("[Onboarding] Logged in user {$user->id} after onboarding via {$transactionId}");

        return redirect()->route('dashboard');
    }

    protected function resolveUserFromKYC(string $transactionId): ?User
    {
        $userId = cache("onboard_user_{$transactionId}");

        return User::find($userId);
    }
}
