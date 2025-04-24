<?php

use function Pest\Laravel\{actingAs, postJson};
use FrittenKeeZ\Vouchers\Models\Voucher;
use App\Commerce\Models\{Order, Vendor};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function () {
    $vendor_id = Vendor::factory()->create()->id;
    $this->vendor = Vendor::find($vendor_id);
});

it('creates an order and voucher successfully', function () {
    Http::fake(); // Prevent real callbacks

    $payload = [
        'reference_id' => Str::uuid(),
        'item_description' => 'Kape Barako',
        'amount' => 199.99,
        'currency' => 'PHP',
        'id_type' => 'philsys',
        'id_value' => '6302-5389-1879-5682',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'callback_url' => 'https://run.mocky.io/v3/123-callback',
    ];
    $response = actingAs($this->vendor, 'sanctum')
        ->postJson(route('api.orders.store'), $payload);
    $response->assertOk()
        ->assertJsonStructure(['success', 'voucher_code', 'url']);
    $voucher_code = $response->json('voucher_code');
    $url = route('vendor.face.payment', ['voucher_code' => $voucher_code]);
    $response->assertJson(['success' => true, 'voucher_code' => $voucher_code, 'url' => $url]);
    expect(Order::where('reference_id', $payload['reference_id'])->exists())->toBeTrue();
});

it('fails validation without required fields', function () {
    actingAs($this->vendor, 'sanctum')
        ->postJson(route('api.orders.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'reference_id',
            'item_description',
            'amount',
            'callback_url',
        ]);
});

it('defaults to PHP currency if not provided', function () {
    $payload = [
        'reference_id' => Str::uuid(),
        'item_description' => 'Face Soap',
        'amount' => 99.50,
        'callback_url' => 'https://run.mocky.io/v3/321-callback',
    ];
    actingAs($this->vendor, 'sanctum')
        ->postJson(route('api.orders.store'), $payload)
        ->assertOk();

    $order = Order::latest()->first();
    expect($order->currency)->toBe('PHP');
});

it('associates voucher with correct owner and entity', function () {
    $referenceId = Str::uuid();
    $payload = [
        'reference_id' => $referenceId,
        'item_description' => 'Barako Coffee',
        'amount' => 150.00,
        'callback_url' => 'https://run.mocky.io/v3/callback-test',
    ];

    $response = actingAs($this->vendor, 'sanctum')->postJson(route('api.orders.store'), $payload);

    $voucher_code = $response->json('voucher_code');
    $voucher = Voucher::where('code', $voucher_code)->first();
    expect($voucher->owner)->toBeInstanceOf(Vendor::class)
        ->and($voucher->owner->is($this->vendor))->toBeTrue()
        ->and($voucher->getEntities())->toHaveCount(1);

    $order = Order::where('reference_id', $referenceId)->firstOrFail();
    expect($voucher->getEntities(Order::class)->first()->is($order))->toBeTrue()
        ->and($order->reference_id)->toBe($referenceId->toString())
    ;
});
