<?php

use App\KYC\Services\FaceVerificationPipeline;
use function Pest\Laravel\actingAs;
use Illuminate\Http\UploadedFile;
use App\Commerce\Models\Vendor;
use App\Models\User;

pest()->extend(Tests\DuskTestCase::class)
//  ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function fakeBase64Image(): string
{
    return 'data:image/jpeg;base64,' . base64_encode(
            \Illuminate\Http\UploadedFile::fake()->image('selfie.jpg')->getContent()
        );
}

function new_vendor_generates_voucher($test): void
{
    $vendor_id = Vendor::factory()->create(['name' => 'The Vendor'])->id;
    $test->vendor = Vendor::find($vendor_id);
    $test->vendor_token = $test->vendor->createToken('vendor-api')->plainTextToken;
    $test->reference_id = 'AA537';
    $test->amount = 250.0;
    $test->payload = [
        'reference_id' => $test->reference_id,
        'item_description' => 'Kape Barako',
        'amount' => $test->amount,
        'currency' => 'PHP',
        'id_type' => 'philsys',
        'id_number' => '6302-5389-1879-5682',
        'email' => 'test@example.com',
        'mobile' => '09171234567',
        'callback_url' => 'https://run.mocky.io/v3/123-callback',
    ];
    $test->voucher_code = actingAs($test->vendor, 'sanctum')
        ->postJson(route('api.orders.store'), $test->payload)
        ->json('voucher_code');
}

function postFacePayment($test, array $overrides = [])
{
    return $test->withToken($test->vendor_token)
        ->post(route('face.payment'), array_merge([
            'voucher_code' => $test->voucher_code,
            'selfie' => 'fake_selfie_base64',
        ], $overrides));
}

function mockFaceVerificationPass() {
    return app()->instance(FaceVerificationPipeline::class, Mockery::mock(FaceVerificationPipeline::class)
        ->shouldReceive('verify')
        ->andReturn([
            'result' => [
                'details' => ['match' => ['value' => 'yes', 'confidence' => 'very_high']],
                'summary' => ['action' => 'pass'],
            ]
        ])->getMock());
}

function attachUserPhoto(User $user): void {
    $user->addMedia(UploadedFile::fake()->image('face.jpg'))
        ->preservingOriginal()
        ->toMediaCollection('photo');
}
