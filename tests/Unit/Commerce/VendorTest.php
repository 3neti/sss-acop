<?php

use App\KYC\Services\FaceVerificationPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Commerce\Models\Vendor;
use App\Models\User;

uses(RefreshDatabase::class);

it('has initial zero balance', function () {
    $vendor = Vendor::factory()->create();
    expect($vendor->balance())->toBe(0.0);
});

it('can receive a payment from user', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(100.00);
    $user->transferFloat($vendor, 75.00);

    expect($vendor->balance())->toBe(75.00)
        ->and($user->balance())->toBe(25.00);
});

it('can transfer funds to another vendor', function () {
    $vendorA = Vendor::factory()->create();
    $vendorB = Vendor::factory()->create();

    $vendorA->depositFloat(200.00);
    $success = $vendorA->transferTo($vendorB, 100.00);

    expect($success)->toBeTrue()
        ->and($vendorA->balance())->toBe(100.00)
        ->and($vendorB->balance())->toBe(100.00);
});

it('cannot transfer if insufficient balance', function () {
    $vendorA = Vendor::factory()->create();
    $vendorB = Vendor::factory()->create();

    $success = $vendorA->transferTo($vendorB, 50.00);

    expect($success)->toBeFalse()
        ->and($vendorA->balance())->toBe(0.0)
        ->and($vendorB->balance())->toBe(0.0);
});

it('can refund payment to user', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();

    $vendor->depositFloat(150.00);
    $success = $vendor->refundTo($user, 50.00);

    expect($success)->toBeTrue()
        ->and($vendor->balance())->toBe(100.00)
        ->and($user->balance())->toBe(50.00);
});

it('can generate a personal access token for vendor', function () {
    $vendor = Vendor::factory()->create(['email' => 'vendor@example.com']);
    $token = $vendor->createToken('vendor-api')->plainTextToken;

    expect($token)->toBeString()->not->toBeEmpty();
});

test('vendor can complete face payment using bearer token', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;

    $user = User::factory()->create([
        'id_number' => 'ABC123',
        'id_type' => 'phl_umid',
    ]);
    $user->depositFloat(300);
    attachUserPhoto($user);

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('verify')->andReturn([
        'result' => [
            'details' => [
                'match' => ['value' => 'yes', 'confidence' => 'very_high'],
            ],
            'summary' => ['action' => 'pass'],
        ]
    ]);
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->withToken($token)->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 250,
        'item_description' => 'Token Meal',
        'reference_id' => 'TXN-BEARER-01',
        'currency' => 'PHP',
        'callback_url' => 'https://vendor.example.com/callback',
        'id_number' => 'ABC123',
        'id_type' => 'phl_umid',
        'selfie' => 'base64stringgoeshere',
    ]);

    $response->assertOk()->assertJsonFragment([
        'message' => 'Payment successful',
        'reference_id' => 'TXN-BEARER-01',
        'item_description' => 'Token Meal',
        'currency' => 'PHP',
    ]);

    expect((float) $user->fresh()->balanceFloat)->toBe(50.0)
        ->and((float) $vendor->balanceFloat)->toBe(250.0);
});

test('unauthorized if bearer token is invalid', function () {
    $vendor = Vendor::factory()->create(); // not used, just here to show valid vendor exists
    $invalidToken = 'Bearer faketoken123';

    $user = User::factory()->create([
        'id_number' => 'XYZ999',
        'id_type' => 'phl_umid',
    ]);
    $user->depositFloat(300);
    attachUserPhoto($user);

    $response = $this->withHeader('Authorization', $invalidToken)->postJson(route('face.payment'), [
        'amount' => 250,
        'item_description' => 'Unauthorized Meal',
        'reference_id' => 'TXN-UNAUTH-01',
        'currency' => 'PHP',
        'callback_url' => 'https://vendor.example.com/callback',
        'id_number' => 'XYZ999',
        'id_type' => 'phl_umid',
        'selfie' => 'base64stringgoeshere',
    ]);

    $response->assertUnauthorized()
        ->assertJsonFragment([
            'message' => 'Unauthenticated.',
        ]);
});
