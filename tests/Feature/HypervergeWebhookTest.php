<?php

use App\KYC\Events\HypervergeStatusReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Event::fake();
    Log::spy();
});

test('valid webhook triggers event and logs payload', function () {
    $payload = [
        'transactionId' => 'txn-001',
        'status' => 'auto_approved',
    ];

    $response = $this->get(route('webhooks.hyperverge', $payload), ['Accept' => 'application/json']);

    $response->assertOk()
        ->assertJson(['message' => 'Webhook received.']);

    Event::assertDispatched(HypervergeStatusReceived::class, function ($event) {
        return $event->transactionId === 'txn-001'
            && $event->status === 'auto_approved';
    });
});

test('invalid webhook with missing fields returns 422', function () {
    $response = $this->get(
        route('webhooks.hyperverge') . '?status=auto_approved',
        ['Accept' => 'application/json']
    );
    $response->assertStatus(422);
});

test('invalid status value returns validation error', function () {
    $query = http_build_query([
        'transactionId' => 'txn-002',
        'status' => 'not_allowed',
    ]);
    $url = route('webhooks.hyperverge') . '?' . $query;
    $response = $this->get($url, ['Accept' => 'application/json']);
    $response->assertStatus(422);
});
