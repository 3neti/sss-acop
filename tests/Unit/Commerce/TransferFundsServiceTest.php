<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Commerce\Services\TransferFundsService;
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
