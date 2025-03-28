<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Actions\MatchFace;
use function Pest\Laravel\post;
use App\Models\User;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create([
        'email' => 'johndoe@example.com',
//        'mobile' => '09171234567',
    ]);

    $this->user->addMedia(UploadedFile::fake()->image('face.jpg'))
        ->preservingOriginal()
        ->toMediaCollection('profile');
});

test('user can login with face using user_id', function () {
    MatchFace::shouldRun()
        ->andReturn((object)[
            'result' => (object)[
                'details' => (object)[
                    'match' => (object)[
                        'value' => 'yes',
                        'confidence' => 99
                    ]
                ],
                'summary' => (object)[
                    'action' => 'pass'
                ]
            ]
        ]);

    $response = post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'base64img' => fakeBase64Image(),
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(Auth::user()->is($this->user))->toBeTrue();
});

test('login fails if face match is unsuccessful', function () {
    MatchFace::shouldRun()
        ->andReturn((object)[
            'result' => (object)[
                'details' => (object)[
                    'match' => (object)[
                        'value' => 'no',
                        'confidence' => 42
                    ]
                ],
                'summary' => (object)[
                    'action' => 'fail'
                ]
            ]
        ]);

    $response = post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'base64img' => fakeBase64Image(),
    ]);

    $response->assertSessionHasErrors('base64img');
    expect(Auth::check())->toBeFalse();
});

test('login fails if required identifier is missing', function () {
    MatchFace::shouldNotRun();

    $response = post(route('face.login.attempt'), [
        'base64img' => fakeBase64Image(),
    ]);

    $response->assertSessionHasErrors('user_id');
});

test('matchface action receives expected arguments', function () {
    $spy = MatchFace::spy();
    $spy->allows('handle')->andReturn((object)[
        'result' => (object)[
            'details' => (object)['match' => (object)['value' => 'yes', 'confidence' => 99]],
            'summary' => (object)['action' => 'pass']
        ]
    ]);

    post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'base64img' => fakeBase64Image(),
    ]);

    $spy->shouldHaveReceived('handle');
});

function fakeBase64Image(): string
{
    $image = UploadedFile::fake()->image('selfie.jpg')->getContent();
    return 'data:image/jpeg;base64,' . base64_encode($image);
}
