<?php

use App\Commerce\Models\System;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use App\Commerce\Events\WalletToppedUp;
use Laravel\Dusk\Browser;
use function Pest\Laravel\actingAs;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

//beforeEach(function () {
//    Event::fake();
//    Cache::flush();
//
//    $this->user = User::factory()->create(['mobile' => '09171234567']);
//    actingAs($this->user);
//
//    $this->system = System::factory()->create();
//    $this->system->depositFloat(10_000_000);
//});

test('basic example', function () {
    $user = User::factory()->create([
        'email' => 'taylor@laravel.com',
        'id_type' => 'phl_dl',
        'id_number' => 'N01-87-049586',
    ]);


    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->assertVisible('#id_number')
            ->type('id_type', $user->id_type)
            ->type('id_number', $user->id_number)
            ->press('Login')
            ->assertPathIs('/home')
        ;
    });
});

//it('generates a QR code via the Vue page', function () {
//    $this->browse(function (Browser $browser) {
//        $browser->visit(route('profile.edit')) // adjust this route if needed
//        ->assertSee('Profile')
////            ->type('amount', '150')
////            ->press('Generate Top-up QR')
////            ->waitForText('Generating QR code...', 5)
////            ->waitFor('img[alt="Top-up QR Code"]', 10)
////            ->assertSee('Scan to Top-Up ₱150')
//        ;
//    });
//});

//it('can top up wallet using a cache key', function () {
//    $amount = 500;
//    $account = $this->user->mobile ?? '09171234567';
//    $cacheKey = "deposit_qr_{$amount}_{$account}";
//
//    Cache::put($cacheKey, [
//        'amount' => $amount,
//        'account' => $account,
//    ], now()->addMinutes(30));
//
//    actingAs($this->user)
//        ->postJson(route('wallet.topup'), [
//            'cacheKey' => $cacheKey,
//        ])
//        ->assertOk()
//        ->assertJson([
//            'success' => true,
//            'message' => 'Wallet topped up successfully.',
//        ]);
//});
