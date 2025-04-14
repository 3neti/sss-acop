<?php

use App\Commerce\Events\TransferRefunded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Commerce\Services\TransferFundsService;
use App\Commerce\Events\TransferInitiated;
use Illuminate\Support\Facades\Event;
use Bavix\Wallet\Models\Transfer;
use App\Commerce\Models\Vendor;
use App\Models\User;

uses(RefreshDatabase::class);

it('can create an unconfirmed transfer via service', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(300);

    $service = new TransferFundsService();
    $transfer = $service->transferUnconfirmed($user, $vendor, 150);

    expect($transfer)->toBeInstanceOf(Transfer::class)
        ->and($transfer->withdraw->confirmed)->toBeFalse()
        ->and($transfer->deposit->confirmed)->toBeFalse()
        ->and((float) $user->balanceFloat)->toBe(300.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});

it('applies balances after confirming transfer via service', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(300);

    $service = new TransferFundsService();
    $transfer = $service->transferUnconfirmed($user, $vendor, 150);

    $result = $service->confirmTransfer($transfer);

    expect($result)->toBeTrue()
        ->and((float) $user->balanceFloat)->toBe(150.0)
        ->and((float) $vendor->balanceFloat)->toBe(150.0)
        ->and($transfer->withdraw->refresh()->confirmed)->toBeTrue()
        ->and($transfer->deposit->refresh()->confirmed)->toBeTrue();
});

it('reverts balances after rollback of confirmed transfer via service', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(300);

    $service = new TransferFundsService();
    $transfer = $service->transferUnconfirmed($user, $vendor, 150);
    $service->confirmTransfer($transfer);

    expect((float) $user->balanceFloat)->toBe(150.0)
        ->and((float) $vendor->balanceFloat)->toBe(150.0);

    $service->rollbackTransfer($transfer);

    expect((float) $user->balanceFloat)->toBe(300.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0)
        ->and($transfer->withdraw->refresh()->confirmed)->toBeFalse()
        ->and($transfer->deposit->refresh()->confirmed)->toBeFalse();
});

it('returns true when resetTransferConfirm is successful', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $user->depositFloat(200);

    $transfer = $user->transferUnconfirmed($vendor, 100);
    $user->confirm($transfer->withdraw);
    $vendor->confirm($transfer->deposit);

    $result = app(TransferFundsService::class)->isResetTransferConfirmSafe($transfer);

    expect($result)->toBeTrue();
});

it('returns false when resetTransferConfirm is called again after already reset', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $user->depositFloat(200);

    $transfer = $user->transferUnconfirmed($vendor, 100);
    $user->confirm($transfer->withdraw);
    $vendor->confirm($transfer->deposit);

    // First reset
    $vendor->resetTransferConfirm($transfer);

    // This one should fail silently in safe mode
    $result = app(TransferFundsService::class)->isResetTransferConfirmSafe($transfer);

    expect($result)->toBeFalse();
});

it('logs metadata on unconfirmed transfer', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $user->depositFloat(200);

    $meta = [
        'initiated_by' => 'face_login',
        'reason' => 'payment for voucher #ABC123'
    ];

    $transfer = app(TransferFundsService::class)->transferUnconfirmed($user, $vendor, 150, $meta);

    expect($transfer->refresh()->extra)->toMatchArray($meta)
        ->and($transfer->withdraw->refresh()->meta)->toMatchArray($meta)
        ->and($transfer->deposit->refresh()->meta)->toMatchArray($meta)
    ;
});

it('dispatches TransferInitiated event on unconfirmed transfer', function () {
    Event::fake();

    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $user->depositFloat(100);

    $meta = [
        'initiated_by' => 'face_login',
        'reason' => 'voucher payment',
    ];

    $transfer = app(TransferFundsService::class)
        ->transferUnconfirmed($user, $vendor, 75, $meta);

    Event::assertDispatched(TransferInitiated::class, function (TransferInitiated $event) use ($transfer, $meta) {
        return $event->uuid === $transfer->uuid
            && $event->fromId === $transfer->from_id
            && $event->toId === $transfer->to_id
            && $event->meta === $meta;
    });
});

it('finalizes a confirmed transfer', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(500);

    $service = new TransferFundsService();
    $transfer = $service->transferUnconfirmed($user, $vendor, 200);
    $service->confirmTransfer($transfer);

    $result = $service->finalizeTransfer($transfer);

    expect($result)->toBeTrue()
        ->and($transfer->refresh()->status)->toBe(\Bavix\Wallet\Models\Transfer::STATUS_PAID);
});

it('does not refund if recipient has insufficient funds', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();

    $vendor->depositFloat(500);
    $service = new TransferFundsService();
    $transfer = $service->transferUnconfirmed($vendor, $user, 400);
    $service->confirmTransfer($transfer);

    // simulate user spending funds
    $user->withdrawFloat(400);

    $refund = $service->refundTransfer($transfer);
    expect($refund)->toBeNull();
});


it('refunds transfer from vendor who started with zero, funded by buyer', function () {
    $buyer = User::factory()->create();     // Acts as the paying user
    $vendor = Vendor::factory()->create();  // Starts with zero

    $service = new TransferFundsService();

    // Step 1: Buyer deposits ₱500
    $buyer->depositFloat(500);
    expect((float) $buyer->balanceFloat)->toBe(500.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);

    // Step 2: Buyer transfers ₱300 to vendor
    $transfer = $service->transferUnconfirmed($buyer, $vendor, 300);
    $service->confirmTransfer($transfer);

    expect((float) $buyer->balanceFloat)->toBe(200.0)
        ->and((float) $vendor->balanceFloat)->toBe(300.0);

    // Step 3: Vendor refunds ₱300 to buyer
    $refund = $service->refundTransfer($transfer);

    // Step 4: Validate refund transfer object
    expect($refund)->not->toBeNull()
        ->and($refund)->toBeInstanceOf(Transfer::class)
        ->and($refund->status)->toBe(Transfer::STATUS_REFUND);

    // Step 5: Validate balances reverted to original
    expect((float) $buyer->balanceFloat)->toBe(500.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});

it('logs and dispatches TransferRefunded on refund', function () {
    Event::fake([TransferRefunded::class]);

    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();

    $user->depositFloat(500);
    $transfer = app(TransferFundsService::class)->transferUnconfirmed($user, $vendor, 300);
    app(TransferFundsService::class)->confirmTransfer($transfer);
    $refund = app(TransferFundsService::class)->refundTransfer($transfer);

    Event::assertDispatched(TransferRefunded::class, function ($event) use ($refund, $transfer) {
        return $event->uuid === $refund->uuid
            && $event->originalUuid === $transfer->uuid;
    });
});
