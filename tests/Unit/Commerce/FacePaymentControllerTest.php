<?php

use Illuminate\Support\Facades\{Config, Http, Storage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\KYC\Services\FaceVerificationPipeline;
use function Pest\Laravel\actingAs;
use App\Models\User;
use Spatie\Url\Url;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Config::set('sss-acop.identifiers', ['id_value', 'id_type']);
    Http::fake(); // Prevent real callbacks
    new_vendor_generates_voucher($this);
});

dataset('user', function () {
    return [
        [fn() => tap(User::factory()->create(['id_value' => '6302-5389-1879-5682', 'id_type' => 'philsys']))->depositFloat(300.0)]
    ];
});

it('completes face payment successfully', function (User $user) {
    attachUserPhoto($user);
    mockFaceVerificationPass();
    $response = postFacePayment($this);
    $response->assertRedirect();
    $redirectUrl = Url::fromString($response->headers->get('Location'));
    expect($redirectUrl->getScheme())->toBe('https')
        ->and($redirectUrl->getHost())->toBe('run.mocky.io')
        ->and($redirectUrl->getPath())->toBe('/v3/123-callback')
        ->and($redirectUrl->getQueryParameter('reference_id'))->toBe($this->reference_id)
        ->and($redirectUrl->getQueryParameter('status'))->toBe('ayus')
        ->and((float)$user->fresh()->balanceFloat)->toBe(50.0)
        ->and((float)$this->vendor->balanceFloat)->toBe(250.0)
    ;
})->with('user');

it('fails with 404 when user not found', function () {
    postFacePayment($this)->assertNotFound();
});

it('fails with 404 when user has no stored photo', function (User $user) {
    app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class)
        ->shouldNotReceive('verify')
        ->getMock());
    postFacePayment($this)
        ->assertNotFound();
})->with('user');

it('fails with 402 when user has insufficient balance', function (User $user) {
    $user->withdrawFloat(250.0);
    attachUserPhoto($user);
    mockFaceVerificationPass();
    expect($user->balanceFloat)->toBeLessThan($this->amount);
    postFacePayment($this)->assertStatus(402);
})->with('user');

it('fails with 403 when face does not match', function (User $user) {
    attachUserPhoto($user);
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
    postFacePayment($this)->assertForbidden();
})->with('user');

it('returns 500 on unexpected exception', function (User $user) {
    attachUserPhoto($user);
    app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class)
        ->shouldReceive('verify')
        ->andThrow(new Exception('Unexpected error'))
        ->getMock()
    );
    postFacePayment($this)->assertStatus(500);
})->with('user');

it('blocks access for non-vendor users', function (User $user) {
    $this->reference_id = 'AA537';//Str::uuid();
    $this->payload = [
        'reference_id' => $this->reference_id,
        'item_description' => 'Kape Barako',
        'amount' => 250.0,
        'currency' => 'PHP',
        'id_type' => 'philsys',
        'id_value' => '6302-5389-1879-5682',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'callback_url' => 'https://run.mocky.io/v3/123-callback',
    ];
    $unauthorized_user = User::factory()->create(['name' => 'Unauthorized User']);
    $response = actingAs($unauthorized_user, 'sanctum')->postJson(route('api.orders.store'), $this->payload);
    $this->voucher_code = $response->json('voucher_code');
    $response->assertUnauthorized();
    attachUserPhoto($user);
    mockFaceVerificationPass();
    $response = postFacePayment($this);
    expect($response->status())->toBe(302);
})->with('user');
