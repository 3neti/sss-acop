<?php

use App\KYC\Exceptions\FacePhotoNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Commerce\Services\TransferFundsService;
use App\KYC\Services\FaceVerificationPipeline;
use App\Commerce\Events\TransferInitiated;
use App\Commerce\Events\TransferRefunded;
use Illuminate\Support\Facades\{Config, DB, Event, Storage};
use App\Commerce\Models\Vendor;
use App\Models\User;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    // Default to id_number + id_type if not explicitly set
    Config::set('sss-acop.identifiers', ['id_number', 'id_type']);
});

function attachUserPhoto(User $user): void {
    $user->addMedia(UploadedFile::fake()->image('profile.jpg'))
        ->preservingOriginal()
        ->toMediaCollection('photo');
}

test('completes face payment successfully', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['id_number' => 'ABC123', 'id_type' => 'phl_umid']);
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
//        'vendor_id' => $vendor->id,
        'amount' => 250,
        'item_description' => 'Regular Meal',
        'reference_id' => 'TXN-001',
        'currency' => 'PHP',
        'callback_url' => 'https://vendor.example.com/callback',
        'id_number' => 'ABC123',
        'id_type' => 'phl_umid',
        'selfie' => 'base64stringgoeshere',
    ]);

    $response->assertOk()->assertJsonFragment([
        'message' => 'Payment successful',
        'reference_id' => 'TXN-001',
        'item_description' => 'Regular Meal',
        'currency' => 'PHP',
    ]);

    expect((float) $user->fresh()->balanceFloat)->toBe(50.0)
        ->and((float) $vendor->balanceFloat)->toBe(250.0);
});

test('returns 404 when user is not found', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;

    $response = $this->withToken($token)->postJson(route('face.payment'), [
//        'vendor_id' => $vendor->id,
        'amount' => 100,
        'item_description' => 'Item X',
        'id_number' => 'N/A',
        'id_type' => 'phl_dl',
        'selfie' => 'base64img',
    ]);

    $response->assertNotFound()
        ->assertJsonFragment(['message' => 'User not found.']);
});

test('returns 404 when photo is missing', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['id_number' => 'ZZZ999', 'id_type' => 'phl_umid']);
    $user->depositFloat(300); // no photo

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldNotReceive('verify');
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->withToken($token)->postJson(route('face.payment'), [
//        'vendor_id' => $vendor->id,
        'amount' => 100,
        'item_description' => 'Item X',
        'id_number' => 'ZZZ999',
        'id_type' => 'phl_umid',
        'selfie' => 'base64img',
    ]);

    $response->assertNotFound()
        ->assertJsonFragment(['message' => 'No stored photo found for user.']);
});

test('returns 402 when user has insufficient balance', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;

    $user = User::factory()->create(['id_number' => 'XYZ456', 'id_type' => 'phl_umid']);
    $user->depositFloat(50);
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
//        'vendor_id' => $vendor->id,
        'amount' => 200,
        'item_description' => 'Item Y',
        'id_number' => 'XYZ456',
        'id_type' => 'phl_umid',
        'selfie' => 'base64img',
    ]);

    $response->assertStatus(402)->assertJson(['message' => 'Insufficient balance.']);
});

test('returns 403 when face does not match', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['id_number' => 'LMN123', 'id_type' => 'phl_umid']);
    $user->depositFloat(500);
    attachUserPhoto($user);

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('verify')->andReturn([
        'result' => [
            'details' => [
                'match' => ['value' => 'no', 'confidence' => 'low'],
            ],
            'summary' => [
                'action' => 'fail',
                'details' => [['message' => 'Face mismatch']],
            ],
        ]
    ]);
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->withToken($token)->postJson(route('face.payment'), [
//        'vendor_id' => $vendor->id,
        'amount' => 300,
        'item_description' => 'Unverified Purchase',
        'id_number' => 'LMN123',
        'id_type' => 'phl_umid',
        'selfie' => 'base64image',
    ]);

    $response->assertForbidden()
        ->assertJson([
            'message' => 'Face verification failed.',
            'reason' => 'Face mismatch',
        ]);
});

test('returns 500 on unexpected exception', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['id_number' => 'BUG123', 'id_type' => 'phl_umid']);
    $user->depositFloat(500);
    attachUserPhoto($user);

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('verify')->andThrow(new Exception('Unexpected error'));
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->withToken($token)->postJson(route('face.payment'), [
//        'vendor_id' => $vendor->id,
        'amount' => 300,
        'item_description' => 'System Crash Test',
        'id_number' => 'BUG123',
        'id_type' => 'phl_umid',
        'selfie' => 'fail-img',
    ]);

    $response->assertStatus(500)
        ->assertJsonFragment(['message' => 'Unable to complete payment.']);
});


test('completes face payment using id_number and id_type', function () {
    Config::set('sss-acop.identifiers', ['id_number', 'id_type']);

    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['id_number' => 'ABC123', 'id_type' => 'phl_umid']);
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
//        'vendor_id' => $vendor->id,
        'amount' => 250,
        'item_description' => 'Regular Meal',
        'reference_id' => 'TXN-001',
        'currency' => 'PHP',
        'callback_url' => 'https://vendor.example.com/callback',
        'id_number' => 'ABC123',
        'id_type' => 'phl_umid',
        'selfie' => 'base64stringgoeshere',
    ]);

    $response->assertOk()->assertJsonFragment([
        'message' => 'Payment successful',
        'reference_id' => 'TXN-001',
        'item_description' => 'Regular Meal',
        'currency' => 'PHP',
    ]);

    expect((float) $user->fresh()->balanceFloat)->toBe(50.0)
        ->and((float) $vendor->balanceFloat)->toBe(250.0);
});

test('completes face payment using email', function () {
    Config::set('sss-acop.identifiers', ['email']);

    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['email' => 'john@example.com']);
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
//        'vendor_id' => $vendor->id,
        'amount' => 250,
        'item_description' => 'Email Pay',
        'email' => 'john@example.com',
        'selfie' => 'base64selfie',
    ]);

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Payment successful']);
});

test('completes face payment using mobile', function () {
    Config::set('sss-acop.identifiers', ['mobile']);

    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['mobile' => '09171234567']);
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
//        'vendor_id' => $vendor->id,
        'amount' => 250,
        'item_description' => 'Mobile Pay',
        'mobile' => '09171234567',
        'selfie' => 'base64mobile',
    ]);

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Payment successful']);
});

test('ensures wallet balances are synced after transfer', function () {
    $vendor = Vendor::factory()->create();
    $token = $vendor->createToken('vendor-api')->plainTextToken;
    $user = User::factory()->create(['id_number' => 'SYNC123', 'id_type' => 'phl_umid']);
    $user->depositFloat(300);
    attachUserPhoto($user);

    app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class)->shouldReceive('verify')->andReturn([
        'result' => [
            'details' => ['match' => ['value' => 'yes', 'confidence' => 'very_high']],
            'summary' => ['action' => 'pass'],
        ]
    ])->getMock());

    $this->withToken($token)->postJson(route('face.payment'), [
//        'vendor_id' => $vendor->id,
        'amount' => 200,
        'item_description' => 'Balance Sync Test',
        'id_number' => 'SYNC123',
        'id_type' => 'phl_umid',
        'selfie' => 'fake-selfie',
    ])->assertOk();

    $vendor->wallet->refreshBalance();
    $user->wallet->refreshBalance();

    expect((float) $user->balanceFloat)->toBe(100.0)
        ->and((float) $vendor->balanceFloat)->toBe(200.0);
});

test('non-vendor user cannot access face payment route', function () {
    $user = User::factory()->create(); // Not a Vendor
    $token = $user->createToken('user-api')->plainTextToken;

    $response = $this->withToken($token)->postJson(route('face.payment'), [
        'amount' => 100,
        'item_description' => 'Blocked Access',
        'id_number' => 'ABC123',
        'id_type' => 'phl_umid',
        'selfie' => 'base64image',
    ]);

    $response->assertUnauthorized()
        ->assertJsonFragment(['message' => 'Unauthorized vendor.']);
});
