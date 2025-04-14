<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Bavix\Wallet\Models\Transfer;
use App\Commerce\Models\Product;
use Whitecube\Price\Price;
use Brick\Money\Money;
use App\Models\User;

uses(RefreshDatabase::class);

it('casts price to Price object on get from minor units', function () {
    $product = Product::factory()->create(['price' => 10000]); // ₱10,000.00

    expect($product->price)->toBeInstanceOf(Price::class)
        ->and($product->price->inclusive()->getAmount()->toFloat())->toEqual(10000.00);
});

//it('stores minor units when setting price using float', function () {
//    $product = Product::factory()->make(); // avoid DB write
//    $product->price = 123.45;
//
//    expect($product->getAttributes()['price'])->toBe(12345);
//});
//
//it('supports setting price using Price or Money instances', function () {
//    $money = Money::of(888.88, 'PHP');
//    $price = new Price($money);
//
//    $product = Product::factory()->create(['price' => $price]);
//
//    expect($product->getAttributes()['price'])->toBe(88888)
//        ->and($product->price->inclusive()->getAmount()->toFloat())->toEqual(888.88);
//});
//
//it('returns correct minor amount from getAmountProduct()', function () {
//    $product = Product::factory()->create(['price' => 200]); // ₱200.00
//
//    $amount = $product->getAmountProduct(User::factory()->create());
//
//    expect($amount)->toBe(200 * 100);
//});
//
//it('allows user to pay for a product', function () {
//    $user = User::factory()->create();
//    $product = Product::factory()->create(['price' => 100]); // ₱100.00
//
//    $user->depositFloat(150); // ₱150.00
//
//    $transfer = $user->pay($product);
//
//    expect($transfer)->toBeInstanceOf(Transfer::class)
//        ->and($transfer->status)->toBe(Transfer::STATUS_PAID)
//        ->and((float) $user->balanceFloat)->toBe(50.00); // remaining balance
//});
//
//it('returns null when using safePay with insufficient balance', function () {
//    $user = User::factory()->create();
//    $product = Product::factory()->create(['price' => 300]); // ₱300.00
//
//    $user->depositFloat(100); // ₱100.00 only
//
//    $transfer = $user->safePay($product);
//
//    expect($transfer)->toBeNull()
//        ->and((float) $user->balanceFloat)->toBe(100.00); // no deduction
//});
