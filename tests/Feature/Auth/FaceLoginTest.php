<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\KYC\Services\FaceVerificationPipeline;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use function Pest\Laravel\post;
use App\KYC\Enums\KYCIdType;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create([
        'id_type' => KYCIdType::PHL_SYS,
        'id_value' => '6302-5389-1879-5682',
        'email' => 'johndoe@example.com',
    ]);
    attachUserPhoto($this->user);
});

test('user can login with face using ID type and value', function () {
    mockFaceVerificationPass();

    post(route('face.login.attempt'), [
        'id_type' => $this->user->id_type->value,
        'id_value' => $this->user->id_value,
        'base64img' => fakeBase64Image(),
    ])->assertRedirect(route('dashboard'));

    expect(Auth::user()->is($this->user))->toBeTrue();
});

test('user can login with face using native field: email', function () {
    mockFaceVerificationPass();

    post(route('face.login.attempt'), [
        'id_type' => 'email',
        'id_value' => $this->user->email,
        'base64img' => fakeBase64Image(),
    ])->assertRedirect(route('dashboard'));

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
        'id_type' => 'email',
        'id_value' => $this->user->email,
        'base64img' => fakeBase64Image(),
    ])->assertSessionHasErrors('base64img');

    expect(Auth::check())->toBeFalse();
});


test('login fails if required identifier is missing', function () {
    mockFaceVerificationPass();

    post(route('face.login.attempt'), [
        'base64img' => fakeBase64Image(),
    ])->assertSessionHasErrors(['id_value', 'id_type']);

    expect(Auth::check())->toBeFalse();
});

test('match face service receives expected arguments', function () {
    $mock = Mockery::mock(FaceVerificationPipeline::class);
    $mock->shouldReceive('verify')
        ->once()
        ->withArgs(function (string $referenceCode, string $base64img, string $storedImagePath) {
            expect($referenceCode)->toStartWith('face_')
                ->and($base64img)->toBeString()
                ->and($storedImagePath)->toBeFile();
            return true;
        })
        ->andReturn([
            'result' => [
                'details' => ['match' => ['value' => 'yes', 'confidence' => 'very_high']],
                'summary' => ['action' => 'pass'],
            ]
        ]);
    app()->instance(FaceVerificationPipeline::class, $mock);

    post(route('face.login.attempt'), [
        'id_type' => 'email',
        'id_value' => $this->user->email,
        'base64img' => fakeBase64Image(),
    ])->assertRedirect(route('dashboard'));

    expect(Auth::user()->is($this->user))->toBeTrue();
});
