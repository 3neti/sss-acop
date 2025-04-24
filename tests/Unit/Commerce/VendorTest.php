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

dataset('user', function () {
    return [
        [fn() => tap(User::factory()->create(['id_number' => '6302-5389-1879-5682', 'id_type' => 'philsys']))->depositFloat(300.0)]
    ];
});

test('vendor can complete face payment using bearer token', function (User $user) {
    new_vendor_generates_voucher($this);
    mockFaceVerificationPass();
    attachUserPhoto($user);

    $response = $this->withToken($this->vendor_token)->post(route('face.payment'), [
        'voucher_code' => $this->voucher_code,
        'selfie' => 'fake_selfie_base64',
    ]);

    $response->assertRedirect();
    expect((float) $user->fresh()->balanceFloat)->toBe(50.0)
        ->and((float) $this->vendor->balanceFloat)->toBe(250.0);
})->with('user');

test('unauthorized if bearer token is invalid', function (User $user) {
    new_vendor_generates_voucher($this);
    mockFaceVerificationPass();
    $invalidToken = 'Bearer faketoken123';

    attachUserPhoto($user);

    $response = $this->withToken('Invalid Token')->post(route('face.payment'), [
        'voucher_code' => $this->voucher_code,
        'selfie' => 'fake_selfie_base64',
    ]);

    $response->assertUnauthorized();
})->with('user')->skip();
