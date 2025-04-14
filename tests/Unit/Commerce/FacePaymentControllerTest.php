<?php

use App\Commerce\Events\TransferRefunded;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use App\Models\User;
use App\Commerce\Models\Product;
use App\Commerce\Models\Vendor;
use App\KYC\Services\FaceVerificationPipeline;
use App\Commerce\Services\TransferFundsService;
use App\Commerce\Events\TransferInitiated;
use App\Commerce\Http\Controllers\FacePaymentController;
use Illuminate\Support\Facades\Http;
use Whitecube\Price\Price;

uses(RefreshDatabase::class);

test('route helper resolves correctly', function () {
    expect(route('face.payment'))->toEndWith('/api/face-payment');
});


it('completes face payment successfully', function () {
    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create(['price' => 250]);

    $user = User::factory()->create();
    $user->depositFloat(300);

    // Fake pipeline to return our user
    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andReturn($user);
    $this->app->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => 'base64stringgoeshere',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Payment successful',
            'user_id' => $user->id,
            'product' => $product->name,
        ]);

    expect((float) $user->balanceFloat)->toBe(50.0) // 300 - 250
    ->and((float) $vendor->balanceFloat)->toBe(250.0);
});

it('processes a face payment and transfers funds from buyer to vendor', function () {
    Event::fake([TransferInitiated::class]);

    $buyer = User::factory()->create();
    $vendor = Vendor::factory()->create();

    // Top up buyer wallet
    $buyer->depositFloat(1000);

    $product = Product::factory()->for($vendor)->create([
        'price' => 400, // stored as minor units
    ]);

    // Fake a base64 selfie input
    $fakeSelfie = base64_encode('fake-image-data');

    // Mock the face verification pipeline to return the buyer
    $pipelineMock = Mockery::mock(FaceVerificationPipeline::class);
    $pipelineMock->shouldReceive('run')
        ->once()
        ->andReturn($buyer);

    $this->app->instance(FaceVerificationPipeline::class, $pipelineMock);

    // Send payment request
    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => $fakeSelfie,
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Payment successful',
            'user_id' => $buyer->id,
            'product' => $product->name,
        ]);

    // Validate event dispatch
    Event::assertDispatched(TransferInitiated::class);

    // Validate balances
    expect((float) $buyer->balanceFloat)->toBe(600.0) // 1000 - 400
    ->and((float) $vendor->balanceFloat)->toBe(400.0);
});

it('fails face payment if user has insufficient balance', function () {
    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create(['price' => 600]);

    $user = User::factory()->create();
    $user->depositFloat(500); // Not enough to cover product price

    // Mock pipeline to return the user
    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andReturn($user);
    $this->app->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => base64_encode('selfie'),
    ]);

    $response->assertStatus(402)
        ->assertJson([
            'message' => 'Insufficient balance.',
        ]);

    expect((float) $user->balanceFloat)->toBe(500.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});

it('fails face payment if face verification throws an exception', function () {
    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create(['price' => 400]);

    // User has enough balance
    $user = User::factory()->create();
    $user->depositFloat(1000);

    // Mock pipeline to throw an exception
    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andThrow(new Exception('Face not matched'));
    $this->app->instance(FaceVerificationPipeline::class, $mockPipeline);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => base64_encode('broken'),
    ]);

    $response->assertStatus(500)
        ->assertJson([
            'message' => 'Unable to complete payment.',
            'error' => 'Face not matched',
        ]);

    expect((float) $user->balanceFloat)->toBe(1000.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});

it('completes face payment with price object (Price)', function () {
    Event::fake([TransferInitiated::class]);

    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create([
        'price' => new Price(Money::of(350.00, 'PHP')), // uses Price object directly
    ]);

    $user = User::factory()->create();
    $user->depositFloat(400);

    $mock = Mockery::mock(FaceVerificationPipeline::class);
    $mock->shouldReceive('run')->andReturn($user);
    app()->instance(FaceVerificationPipeline::class, $mock);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => 'fakebase64image',
    ]);

    $response->assertOk()
        ->assertJsonFragment([
            'message' => 'Payment successful',
            'user_id' => $user->id,
            'product' => $product->name,
        ]);

    Event::assertDispatched(TransferInitiated::class);

    expect((float) $user->balanceFloat)->toBe(50.0)
        ->and((float) $vendor->balanceFloat)->toBe(350.0);
});

it('completes face payment with raw float amount', function () {
    Event::fake([TransferInitiated::class]);

    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create([
        'price' => 600.0, // float stored
    ]);

    $user = User::factory()->create();
    $user->depositFloat(1000);

    $mock = Mockery::mock(FaceVerificationPipeline::class);
    $mock->shouldReceive('run')->andReturn($user);
    app()->instance(FaceVerificationPipeline::class, $mock);

    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => 'base64imgdata',
    ]);

    $response->assertOk()
        ->assertJsonFragment([
            'message' => 'Payment successful',
            'user_id' => $user->id,
            'product' => $product->name,
        ]);

    Event::assertDispatched(TransferInitiated::class);

    expect((float) $user->balanceFloat)->toBe(400.0)
        ->and((float) $vendor->balanceFloat)->toBe(600.0);
});

it('processes a refund after successful face payment', function () {
    Event::fake([TransferRefunded::class]);

    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create(['price' => 300]);

    $user = User::factory()->create();
    $user->depositFloat(500);

    // Mock face pipeline
    $mockPipeline = Mockery::mock(FaceVerificationPipeline::class);
    $mockPipeline->shouldReceive('run')->andReturn($user);
    $this->app->instance(FaceVerificationPipeline::class, $mockPipeline);

    // Pay using face
    $response = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => 'fake-base64-img',
    ]);

    $response->assertOk();
    $transferUuid = $response->json('transfer_uuid');
    $transfer = \Bavix\Wallet\Models\Transfer::where('uuid', $transferUuid)->first();

    expect($transfer)->not->toBeNull()
        ->and((float) $vendor->balanceFloat)->toBe(300.0)
        ->and((float) $user->balanceFloat)->toBe(200.0);

    // Initiate refund
    $refund = app(TransferFundsService::class)->refundTransfer($transfer);

    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe(\Bavix\Wallet\Models\Transfer::STATUS_REFUND)
        ->and((float) $user->fresh()->balanceFloat)->toBe(500.0)
        ->and((float) $vendor->fresh()->balanceFloat)->toBe(0.0);

    Event::assertDispatched(TransferRefunded::class);
});

it('allows retry on face verification failure and succeeds on second try', function () {
    $vendor = Vendor::factory()->create();
    $product = Product::factory()->for($vendor)->create(['price' => 200]);
    $user = User::factory()->create();
    $user->depositFloat(300);

    // First attempt: face verification fails
    $this->app->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class, function ($mock) {
        $mock->shouldReceive('run')->once()->andThrow(new \Exception('Liveliness failed.'));
    }));

    $failResponse = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => 'fail-first-time',
    ]);

    $failResponse->assertStatus(500)
        ->assertJsonFragment(['message' => 'Unable to complete payment.']);

    // Second attempt: face verification succeeds
    $this->app->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class, function ($mock) use ($user) {
        $mock->shouldReceive('run')->once()->andReturn($user);
    }));

    $successResponse = $this->postJson(route('face.payment'), [
        'vendor_id' => $vendor->id,
        'product_id' => $product->id,
        'selfie' => 'succeed-now',
    ]);

    $successResponse->assertOk()
        ->assertJson([
            'message' => 'Payment successful',
            'product' => $product->name,
        ]);

    expect((float) $user->balanceFloat)->toBe(100.0)
        ->and((float) $vendor->balanceFloat)->toBe(200.0);
});
