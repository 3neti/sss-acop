<?php

use App\Services\FaceMatch\MatchFaceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\post;
use App\Models\User;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create([
        'email' => 'johndoe@example.com',
    ]);

    $this->user->addMedia(UploadedFile::fake()->image('face.jpg'))
        ->preservingOriginal()
        ->toMediaCollection('profile');
});

test('user can login with face using user_id', function () {
    $mock = Mockery::mock(MatchFaceService::class);
    $mock->shouldReceive('match')->once()->andReturn([
        'result' => [
            'details' => [
                'match' => [
                    'value' => 'yes',
                    'confidence' => 'high',
                ]
            ],
            'summary' => [
                'action' => 'pass',
            ]
        ]
    ]);
    app()->instance(MatchFaceService::class, $mock);

    $response = post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'base64img' => fakeBase64Image(),
    ]);

    $response->assertRedirect(route('dashboard'));
    expect(Auth::user()->is($this->user))->toBeTrue();
});

test('login fails if face match is unsuccessful', function () {
    $mock = Mockery::mock(MatchFaceService::class);
    $mock->shouldReceive('match')->once()->andReturn([
        'result' => [
            'details' => [
                'match' => [
                    'value' => 'no',
                    'confidence' => 'low',
                ]
            ],
            'summary' => [
                'action' => 'fail',
            ]
        ]
    ]);
    app()->instance(MatchFaceService::class, $mock);

    $response = post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'base64img' => fakeBase64Image(),
    ]);

    $response->assertSessionHasErrors('base64img');
    expect(Auth::check())->toBeFalse();
});

test('login fails if required identifier is missing', function () {
    $mock = Mockery::mock(MatchFaceService::class);
    $mock->shouldNotReceive('match');
    app()->instance(MatchFaceService::class, $mock);

    $response = post(route('face.login.attempt'), [
        'base64img' => fakeBase64Image(),
    ]);

    $response->assertSessionHasErrors('user_id');
});

test('match face service receives expected arguments', function () {
    $spy = Mockery::spy(MatchFaceService::class);
    $spy->shouldReceive('match')->once()->andReturn([
        'result' => [
            'details' => [
                'match' => [
                    'value' => 'yes',
                    'confidence' => 'high',
                ]
            ],
            'summary' => [
                'action' => 'pass',
            ]
        ]
    ]);
    app()->instance(MatchFaceService::class, $spy);

    post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'base64img' => fakeBase64Image(),
    ]);

    $spy->shouldHaveReceived('match');
});
