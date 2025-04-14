<?php

use App\Commerce\Events\TransferRefunded;
use App\Commerce\Events\TransferInitiated;
use App\Commerce\Services\TransferFundsService;
use App\KYC\Services\FaceVerificationPipeline;
use App\Models\User;
use App\Commerce\Models\Vendor;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('route helper resolves correctly', function () {
    expect(route('face.payment'))->toEndWith('/api/face-payment');
});

it('completes face payment successfully', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();
    $user->depositFloat(300);

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andReturn($user);
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 250,
        'item_description' => 'Regular Meal',
        'reference_id' => 'TXN-001',
        'selfie' => 'base64stringgoeshere',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Payment successful',
            'user_id' => $user->id,
            'item_description' => 'Regular Meal',
            'reference_id' => 'TXN-001',
        ]);

    expect((float) $user->balanceFloat)->toBe(50.0)
        ->and((float) $vendor->balanceFloat)->toBe(250.0);
});

it('fails face payment if user has insufficient balance', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();
    $user->depositFloat(100);

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andReturn($user);
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 200,
        'item_description' => 'Premium Coffee',
        'reference_id' => 'TXN-002',
        'selfie' => base64_encode('selfie'),
    ]);

    $response->assertStatus(402)
        ->assertJson(['message' => 'Insufficient balance.']);

    expect((float) $user->balanceFloat)->toBe(100.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});

it('fails face payment if face verification throws an exception', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();
    $user->depositFloat(500);

    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andThrow(new Exception('Face not matched'));
    app()->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 300,
        'item_description' => 'Lunch Combo',
        'reference_id' => 'TXN-003',
        'selfie' => base64_encode('bad-img'),
    ]);

    $response->assertStatus(500)
        ->assertJsonFragment(['error' => 'Face not matched']);
});

it('dispatches TransferInitiated and saves metadata', function () {
    Event::fake([TransferInitiated::class]);

    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();
    $user->depositFloat(600);

    $mock = Mockery::mock(FaceVerificationPipeline::class);
    $mock->shouldReceive('run')->andReturn($user);
    app()->instance(FaceVerificationPipeline::class, $mock);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 450,
        'item_description' => 'Dinner Box',
        'reference_id' => 'TXN-004',
        'selfie' => 'selfie-string',
    ]);

    $response->assertOk()
        ->assertJsonFragment(['item_description' => 'Dinner Box']);

    Event::assertDispatched(TransferInitiated::class);

    expect((float) $user->balanceFloat)->toBe(150.0)
        ->and((float) $vendor->balanceFloat)->toBe(450.0);
});

it('processes a refund after successful face payment', function () {
    Event::fake([TransferRefunded::class]);

    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();
    $user->depositFloat(500);

    $mock = Mockery::mock(FaceVerificationPipeline::class);
    $mock->shouldReceive('run')->andReturn($user);
    app()->instance(FaceVerificationPipeline::class, $mock);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 300,
        'item_description' => 'Rice Bowl',
        'reference_id' => 'TXN-005',
        'selfie' => 'face-base64',
    ]);

    $response->assertOk();
    $uuid = $response->json('transfer_uuid');
    $transfer = \Bavix\Wallet\Models\Transfer::where('uuid', $uuid)->first();

    $refund = app(TransferFundsService::class)->refundTransfer($transfer);

    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(\Bavix\Wallet\Models\Transfer::STATUS_REFUND)
        ->and((float) $user->fresh()->balanceFloat)->toBe(500.0)
        ->and((float) $vendor->fresh()->balanceFloat)->toBe(0.0);

    Event::assertDispatched(TransferRefunded::class);
});

it('allows retry on face verification failure and succeeds on second try', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();
    $user->depositFloat(300);

    // First attempt fails
    app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andThrow(new \Exception('Liveliness failed.'));
    }));

    $failResponse = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 200,
        'item_description' => 'Sizzling Plate',
        'reference_id' => 'TXN-006',
        'selfie' => 'fail-first',
    ]);

    $failResponse->assertStatus(500)
        ->assertJsonFragment(['message' => 'Unable to complete payment.']);

    // Second attempt succeeds
    app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class, function ($mock) use ($user) {
        $mock->shouldReceive('run')->once()->andReturn($user);
    }));

    $successResponse = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'amount' => 200,
        'item_description' => 'Sizzling Plate',
        'reference_id' => 'TXN-006',
        'selfie' => 'second-try',
    ]);

    $successResponse->assertOk()
        ->assertJson([
            'message' => 'Payment successful',
            'item_description' => 'Sizzling Plate',
            'reference_id' => 'TXN-006',
        ]);

    expect((float) $user->balanceFloat)->toBe(100.0)
        ->and((float) $vendor->balanceFloat)->toBe(200.0);
});
