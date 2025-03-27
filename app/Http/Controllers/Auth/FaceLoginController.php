<?php

namespace App\Http\Controllers\Auth;

use App\Actions\MatchFace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;

class FaceLoginController extends Controller
{
    public function showForm()
    {
        return inertia('Auth/FaceLogin', [
            'autoFaceLogin' => config('sss-acop.auto_face_login')
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'base64img' => ['required', 'string'],
        ]);

        try {
            // 1. Find user by email
            $user = User::where('email', $request->email)->firstOrFail();

            // 2. Get stored profile image path
            $media = $user->getFirstMedia('profile');
            if (!$media) {
                return back()->withErrors(['email' => 'No profile image found for facial match.']);
            }

            $storedImagePath = Storage::disk($media->disk)->path($media->getPathRelativeToRoot());
            $referenceCode = uniqid();

            // 3. Call the MatchFace action
            $result = MatchFace::run(
                referenceCode: $referenceCode,
                base64img: $request->base64img,
                storedImagePath: $storedImagePath,
                type: $request->type // optional
            );

            $match = $result->result->details->match->value ?? null;
            $confidence = $result->result->details->match->confidence ?? null;
            $action = $result->result->summary->action ?? null;

            if ($match === 'yes' && $action === 'pass') {
                Auth::login($user);
                $request->session()->regenerate();

                return redirect()->intended(route('dashboard'));
            }

            return back()->withErrors([
                'base64img' => 'Face verification failed. Match: ' . $match . ', Confidence: ' . $confidence,
            ]);
        } catch (Exception $e) {
            report($e);
            return back()->withErrors([
                'base64img' => 'Face login failed. ' . $e->getMessage(),
            ]);
        }
    }
}
