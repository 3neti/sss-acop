<?php

use App\KYC\Actions\GenerateLink;
use Illuminate\Support\Facades\Http;

test('GenerateLink returns valid URL on success', function () {
    Http::fake([
        'https://ind.idv.hyperverge.co/v1/link-kyc/start' => Http::response([
            'status' => 'success',
            'statusCode' => 200,
            'result' => [
                'startKycUrl' => 'https://link-kyc.idv.hyperverge.co/?identifier=test-transaction-id'
            ],
        ], 200),
    ]);

    $url = GenerateLink::get('test-transaction-id');

    expect($url)->toBeString()
        ->and(filter_var($url, FILTER_VALIDATE_URL))->toBeTruthy();
});

test('GenerateLink throws exception for invalid URL', function () {
    Http::fake([
        'https://ind.idv.hyperverge.co/v1/link-kyc/start' => Http::response([
            'status' => 'success',
            'statusCode' => 200,
            'result' => [
                'startKycUrl' => 'not-a-valid-url'
            ],
        ], 200),
    ]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Invalid KYC link URL received from Hyperverge.');

    GenerateLink::get('test-invalid-url');
});

test('GenerateLink throws exception on HTTP failure', function () {
    Http::fake([
        'https://ind.idv.hyperverge.co/v1/link-kyc/start' => Http::response([
            'status' => 'failure',
            'statusCode' => 400,
            'message' => 'Bad Request',
        ], 400),
    ]);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Hyperverge onboarding link creation failed');

    GenerateLink::get('test-failure');
});
