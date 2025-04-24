<?php

use Illuminate\Support\Facades\{Event, Cache, Log};
use App\KYC\Events\HypervergeStatusReceived;
use function Pest\Laravel\get;
use Illuminate\Support\Str;

beforeEach(function () {
    Event::fake();
    Cache::flush();
    Log::spy();
});

it('accepts a valid GET payload and redirects with cached status', function () {
    $transactionId = Str::uuid()->toString();
    $status = 'auto_approved';

    $response = get(route('webhooks.hyperverge', [
        'transactionId' => $transactionId,
        'status' => $status,
    ]));

    $response->assertRedirect(route('onboarding.status', ['transactionId' => $transactionId]));

    Event::assertDispatched(HypervergeStatusReceived::class, function ($event) use ($transactionId, $status) {
        return $event->transactionId === $transactionId && $event->status === $status;
    });

    expect(Cache::get("kyc_status_{$transactionId}"))->toBe($status);
});

it('rejects a missing transactionId', function () {
    $response = get(route('webhooks.hyperverge', [
        'status' => 'auto_approved',
    ]));

    $response->assertStatus(302); // Laravel redirects to previous page on validation failure
    $response->assertSessionHasErrors('transactionId');
});

it('rejects an invalid status', function () {
    $response = get(route('webhooks.hyperverge', [
        'transactionId' => Str::uuid()->toString(),
        'status' => 'invalid_status',
    ]));

    $response->assertStatus(302);
    $response->assertSessionHasErrors('status');
});
