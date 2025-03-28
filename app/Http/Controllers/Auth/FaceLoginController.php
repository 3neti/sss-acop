<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Actions\MatchFace;
use App\Models\User;
use Exception;

class FaceLoginController extends Controller
{
    protected array $fields = ['user_id'];

    protected array $identifiers = [
        'email' => 'email',
        'mobile' => 'mobile',
        'user_id' => 'id',
    ];

    public function showForm()
    {
        return inertia('Auth/FaceLogin', [
            'fields' => $this->fields, //array
            'autoFaceLogin' => config('sss-acop.auto_face_login')
        ]);
    }

    public function authenticate(Request $request)
    {
        $request->validate($this->rules());

        // 1. Find user by identifier
        $userQuery = User::query();
        $foundField = null;

        foreach ($this->identifiers as $inputField => $column) {
            if ($request->filled($inputField)) {
                $userQuery->where($column, $request->input($inputField));
                $foundField = $inputField;
                break;
            }
        }

        if (!$foundField) {
            return back()->withErrors(['base64img' => 'No valid identifier provided.']);
        }

        $user = $userQuery->first();

        if (!$user) {
            return back()->withErrors([$foundField => 'No user found with the given credentials.']);
        }

        // 2. Get stored profile image path
        $media = $user->getFirstMedia('profile');
        if (!$media) {
            return back()->withErrors(['email' => 'No profile image found for facial match.']);
        }

        try {
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

    public function rules(): array
    {
        $rules = [
            'base64img' => ['required', 'string'],
        ];

        foreach ($this->fields as $field) {
            $rules[$field] = match ($field) {
                'email' => ['required', 'email', 'exists:users,email'],
                'mobile' => ['required', 'regex:/^09\d{9}$/', 'exists:users,mobile'],
                'user_id' => ['required', 'integer', 'exists:users,id'],
                default => ['required'], // fallback for other fields
            };
        }

        return $rules;
    }
}
