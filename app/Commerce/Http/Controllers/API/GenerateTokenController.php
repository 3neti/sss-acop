<?php

namespace App\Commerce\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerateTokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'tokenName' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $token = $user->createToken($request->tokenName, ['server:update']);

        return response()->json([
            'success' => true,
            'token' => $token->plainTextToken,
        ]);
    }
}
