<?php

use Illuminate\Support\Facades\Log;
use function Pest\Laravel\postJson;

test('hyperverge webhook receives payload and logs it', function () {
    Log::shouldReceive('info')
        ->once()
        ->with('[HypervergeWebhook] Payload received', \Mockery::type('array'));

    $response = postJson(route('webhooks.hyperverge'), [
        'metadata' => [
            'transactionId' => 'txn_12345',
            'requestId' => 'req_67890',
        ],
        'result' => [
            'status' => 'success',
            'summary' => [
                'action' => 'pass',
                'details' => []
            ]
        ]
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Webhook received.']);
});
