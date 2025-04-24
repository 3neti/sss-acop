<?php

use App\KYC\Services\FaceVerificationPipeline;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use function Pest\Laravel\post;
use App\Models\User;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create([
        'email' => 'johndoe@example.com',
    ]);
    attachUserPhoto($this->user);
});

test('user can login with face using user_id', function () {
    mockFaceVerificationPass();
    post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'id_type' => $this->user->id_type->value,
        'id_value' => $this->user->id_value,
        'base64img' => fakeBase64Image(),
    ])->assertRedirect('dashboard');
    expect(Auth::user()->is($this->user))->toBeTrue();
});

test('login fails if face match is unsuccessful', function () {
    app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class)
        ->shouldReceive('verify')
        ->andReturn([
            'result' => [
                'details' => ['match' => ['value' => 'no', 'confidence' => 'low']],
                'summary' => [
                    'action' => 'fail',
                    'details' => [['message' => 'Face mismatch']],
                ],
            ]
        ])->getMock()
    );
    post(route('face.login.attempt'), [
        'user_id' => $this->user->id,
        'id_type' => $this->user->id_type->value,
        'id_value' => $this->user->id_value,
        'base64img' => fakeBase64Image(),
    ])->assertSessionHasErrors('base64img');
    expect(Auth::check())->toBeFalse();
});

test('login fails if required identifier is missing', function () {
    mockFaceVerificationPass();
    post(route('face.login.attempt'), [
        // Intentionally omitting id_value and id_type
        'base64img' => fakeBase64Image(),
    ])->assertSessionHasErrors(['id_value', 'id_type']);
    expect(Auth::check())->toBeFalse();
});

test('match face service receives expected arguments', function () {
    $mock = Mockery::mock(FaceVerificationPipeline::class);
    $mock->shouldReceive('verify')
        ->once()
        ->withArgs(function (string $referenceCode, string $base64img, string $storedImagePath) {
            expect($referenceCode)->toStartWith('face_');
            expect($base64img)->toBeString();
            expect($storedImagePath)->toBeFile();
            return true;
        })
        ->andReturn([
            'result' => [
                'details' => ['match' => ['value' => 'yes', 'confidence' => 'very_high']],
                'summary' => ['action' => 'pass'],
            ]
        ]);
    app()->instance(FaceVerificationPipeline::class, $mock);
    $user = User::factory()->create([
        'id_value' => '6302-5389-1879-5682',
        'id_type' => 'philsys',
    ]);
    attachUserPhoto($user);
    post(route('face.login.attempt'), [
        'id_value' => $user->id_value,
        'id_type' => $user->id_type->value,
        'base64img' => fakeBase64Image(),
    ])->assertRedirect(route('dashboard'));
    expect(Auth::user()->is($user))->toBeTrue();
});
