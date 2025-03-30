<?php

use App\KYC\Services\LivelinessService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('successful liveliness check returns result array', function () {
    Http::fake([
        '*' => Http::response([
            'status' => 'success',
            'statusCode' => 200,
            'result' => [
                'details' => [
                    'liveFace' => ['value' => 'yes', 'confidence' => 'high']
                ],
                'summary' => ['action' => 'pass']
            ]
        ], 200),
    ]);

    $service = new LivelinessService();
    $result = $service->verify('liveliness-001', fakeBase64Image());

    expect($result['result']['details']['liveFace']['value'])->toBe('yes');
    expect($result['result']['summary']['action'])->toBe('pass');
});
