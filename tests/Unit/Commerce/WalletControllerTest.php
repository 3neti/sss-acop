<?php

use App\Commerce\Models\System;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Commerce\Events\WalletToppedUp;
use App\Commerce\Actions\TopupWallet;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    // Fake dependencies
    Event::fake();
    Cache::flush();
    Log::spy();

    // Create and authenticate a test user
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->system = System::factory()->create();
    $this->system->depositFloat(10000000);
});

it('can generate a deposit QR code and cache it', function () {
    $payload = [
        'amount' => 100,
        'account' => '09171234567',
    ];
//dd(route('wallet.qr-code'));
    $response = $this->postJson(route('wallet.qr-code'), $payload);

    $response->assertOk()
        ->assertJsonStructure(['success', 'qr_code'])
        ->assertJson([
            'success' => true,
        ]);

    expect(Cache::has('deposit_qr_100_09171234567'))->toBeTrue();
});

//it('fails QR code generation with invalid input', function () {
//    $response = $this->postJson(route('wallet.qr-code'), [
//        'amount' => 20, // less than minimum
//    ]);
//
//    $response->assertStatus(422);
//});
//
//it('can top up wallet using cached QR data', function () {
//    $cacheKey = 'deposit_qr_200_09181234567';
//    Cache::put($cacheKey, [
//        'amount' => 200,
//        'account' => '09181234567',
//    ], now()->addMinutes(30));
//
//    $response = $this->postJson(route('wallet.topup'), [
//        'cacheKey' => $cacheKey,
//    ]);
//
//    $response->assertOk()
//        ->assertJsonStructure(['success', 'message', 'transfer' => ['id', 'amount', 'meta']])
//        ->assertJson(['success' => true,])
//    ;
//
//    Event::assertDispatched(WalletToppedUp::class);
//});
//
//it('fails top up when cache is missing', function () {
//    $response = $this->postJson(route('wallet.topup'), [
//        'cacheKey' => 'missing_key_123',
//    ]);
//
//    $response->assertStatus(404)
//        ->assertNotFound()
//        ->assertJson([
//            'message' => 'Top-up data not found. The QR code may have expired.',
//        ]);
//});
