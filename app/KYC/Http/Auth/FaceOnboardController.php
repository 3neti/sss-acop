<?php

namespace App\KYC\Http\Auth;

use App\Http\Controllers\Controller;
use App\KYC\Actions\GenerateLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FaceOnboardController extends Controller
{

    public function __invoke(Request $request)
    {
        $transactionId = Str::uuid()->toString();
        $workflowId = 'onboarding';
        $url = GenerateLink::get($transactionId, null, [
            'workflowId' => $workflowId
        ]);

        return inertia()->location($url);
    }
}
