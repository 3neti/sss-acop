<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\User;
use Inertia\Inertia;

class TestController extends Controller
{
//    protected function getBase64(User $user)
//    {
//        $media = $user->getFirstMedia('profile');
//
//        if ($media && Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
//            $fileContents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
//            $mimeType = $media->mime_type;
//
//            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContents);
//        }
//    }



    public function showBase64Page(Request $request)
    {
        $user = $request->user(); // Or use User::find($id) if needed

        $media = $user->getFirstMedia('profile');
        $base64 = null;

        if ($media && Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
            $fileContents = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
            $mimeType = $media->mime_type;

            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($fileContents);
        }

        return Inertia::render('Test/Base64Preview', [
            'imageBase64' => $base64,
        ]);
    }
}
