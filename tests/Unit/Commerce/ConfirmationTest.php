<?php

use App\Commerce\Services\TransferFundsService;
use Bavix\Wallet\Exceptions\{ConfirmedInvalid, UnconfirmedInvalid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Bavix\Wallet\Models\Transaction;
use App\Commerce\Models\Vendor;
use App\Models\User;

uses(RefreshDatabase::class);

it('does not immediately reflect balance on unconfirmed deposit', function () {
    $user = User::factory()->create();

    $transaction = $user->depositFloat(100, meta: [], confirmed: false);

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->confirmed)->toBeFalse()
        ->and((float) $user->balanceFloat)->toBe(0.0);
});

it('reflects balance only after confirmation', function () {
    $user = User::factory()->create();

    $transaction = $user->depositFloat(100, meta: [], confirmed: false);
    $user->confirm($transaction);

    expect($transaction->refresh()->confirmed)->toBeTrue()
        ->and((float) $user->balanceFloat)->toBe(100.0);
});

it('can confirm transfer from user to vendor', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(200);

    // 🧩 Use the trait method directly
    $transfer = $user->transferUnconfirmed($vendor, 100);

    expect((float) $user->balanceFloat)->toBe(200.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);

    $user->confirm($transfer->withdraw);
    $vendor->confirm($transfer->deposit);

    expect((float) $user->balanceFloat)->toBe(100.0)
        ->and((float) $vendor->balanceFloat)->toBe(100.0)
        ->and($transfer->withdraw->refresh()->confirmed)->toBeTrue()
        ->and($transfer->deposit->refresh()->confirmed)->toBeTrue();
});

it('does not apply unconfirmed transfer to vendor', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(100);

    // 🧩 Use trait-based unconfirmed transfer
    $transfer = $user->transferUnconfirmed($vendor, 100);

    // 🧪 Assert: no balance impact until confirmed
    expect((float) $user->balanceFloat)->toBe(100.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0)
        ->and($transfer->withdraw->confirmed)->toBeFalse()
        ->and($transfer->deposit->confirmed)->toBeFalse();
});

it('does not double apply balance if confirmed twice', function () {
    $user = User::factory()->create();
    $transaction = $user->depositFloat(150, confirmed: false);

    $user->confirm($transaction);

    try {
        $user->confirm($transaction); // Attempting to confirm again
    } catch (ConfirmedInvalid) {
        // Expected behavior: already confirmed
    }

    expect($transaction->refresh()->confirmed)->toBeTrue()
        ->and((float) $user->balanceFloat)->toBe(150.0);
});

it('does not double apply balance if confirmed twice - using safeConfirm', function () {
    $user = User::factory()->create();
    $transaction = $user->depositFloat(150, confirmed: false);

    $user->confirm($transaction);
    $result = $user->safeConfirm($transaction); // won't throw

    expect($result)->toBeTrue()
        ->and($transaction->refresh()->confirmed)->toBeTrue()
        ->and((float) $user->balanceFloat)->toBe(150.0);
});


it('can force confirm transfer from vendor to user', function () {
    $vendor = Vendor::factory()->create();
    $user = User::factory()->create();

    $vendor->depositFloat(1000);

    $transfer = $vendor->transferUnconfirmed($user, 250);

    $vendor->confirm($transfer->withdraw);
    $user->confirm($transfer->deposit);

    expect((float) $vendor->balanceFloat)->toBe(750.0)
        ->and((float) $user->balanceFloat)->toBe(250.0);
});

it('fails silently if confirming already confirmed transaction', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    // Scenario 1: deposit
    $depositTx = $user->depositFloat(100, confirmed: false);
    $user->confirm($depositTx);

    $silentConfirmDeposit = $user->safeConfirm($depositTx);

    // Scenario 2: transfer unconfirmed
    $user->depositFloat(100); // re-deposit
    $transfer = $user->transferUnconfirmed($vendor, 100);

    $user->confirm($transfer->withdraw);
    $vendor->confirm($transfer->deposit);

    // Attempting double confirm
    $retryWithdraw = $user->safeConfirm($transfer->withdraw);
    $retryDeposit = $vendor->safeConfirm($transfer->deposit);

    expect($silentConfirmDeposit)->toBeTrue()
        ->and($depositTx->refresh()->confirmed)->toBeTrue()
        ->and($retryWithdraw)->toBeTrue()
        ->and($retryDeposit)->toBeTrue()
        ->and((float) $user->balanceFloat)->toBe(100.0) // 200 - 100 transfer
        ->and((float) $vendor->balanceFloat)->toBe(100.0);
});

it('throws ConfirmedInvalid if confirming an already confirmed transaction', function () {
    $user = User::factory()->create();
    $tx = $user->depositFloat(100);

    $user->confirm($tx);
})->throws(ConfirmedInvalid::class);

//it('can reset a confirmed transaction', function () {
//    $user = User::factory()->create();
//
//    $tx = $user->depositFloat(300, confirmed: false);
//    $user->confirm($tx);
//
//    expect((float) $user->balanceFloat)->toBe(300.0)
//        ->and($tx->refresh()->confirmed)->toBeTrue();
//
//    $user->resetConfirm($tx);
//
//    expect((float) $user->balanceFloat)->toBe(0.0)
//        ->and($tx->refresh()->confirmed)->toBeFalse();
//});

it('can reset confirmation on both sides of an unconfirmed transfer', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(500);

    $transfer = $user->transferUnconfirmed($vendor, 200);

    // ✅ Confirm both sides
    $user->confirm($transfer->withdraw);
    $vendor->confirm($transfer->deposit);

    expect((float) $user->balanceFloat)->toBe(300.0)
        ->and((float) $vendor->balanceFloat)->toBe(200.0)
        ->and($transfer->withdraw->refresh()->confirmed)->toBeTrue()
        ->and($transfer->deposit->refresh()->confirmed)->toBeTrue();

    // 🔄 Reset confirmation on both sides
//    $user->resetTransferConfirm($transfer);
    $vendor->resetTransferConfirm($transfer);

    expect((float) $user->balanceFloat)->toBe(500.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0)
        ->and($transfer->withdraw->refresh()->confirmed)->toBeFalse()
        ->and($transfer->deposit->refresh()->confirmed)->toBeFalse();
});

it('resetTransferConfirm is idempotent - try/catch version', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();

    $user->depositFloat(300);
    $transfer = $user->transferUnconfirmed($vendor, 100);

    $user->confirm($transfer->withdraw);
    $vendor->confirm($transfer->deposit);

    $vendor->resetTransferConfirm($transfer);

    try {
        $vendor->resetTransferConfirm($transfer); // throws UnconfirmedInvalid
    } catch (\Throwable $e) {
        expect($e)->toBeInstanceOf(\Bavix\Wallet\Exceptions\UnconfirmedInvalid::class);
    }

    expect($transfer->withdraw->refresh()->confirmed)->toBeFalse()
        ->and($transfer->deposit->refresh()->confirmed)->toBeFalse()
        ->and((float) $user->balanceFloat)->toBe(300.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});

it('throws if trying to reset an unconfirmed transaction', function () {
    $user = User::factory()->create();
    $tx = $user->depositFloat(100, confirmed: false);

    $user->resetConfirm($tx); // should throw
})->throws(UnconfirmedInvalid::class);

//use App\Commerce\Services\TransferFundsService;

it('confirms transfer via TransferFundsService', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $service = app(TransferFundsService::class);

    $user->depositFloat(500);
    $transfer = $service->transferUnconfirmed($user, $vendor, 200);

    $service->confirmTransfer($transfer);

    expect($transfer->withdraw->refresh()->confirmed)->toBeTrue()
        ->and($transfer->deposit->refresh()->confirmed)->toBeTrue()
        ->and((float) $user->balanceFloat)->toBe(300.0)
        ->and((float) $vendor->balanceFloat)->toBe(200.0);
});

it('can rollback transfer via TransferFundsService', function () {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $service = app(TransferFundsService::class);

    $user->depositFloat(400);
    $transfer = $service->transferUnconfirmed($user, $vendor, 150);

    $service->confirmTransfer($transfer);
    $service->rollbackTransfer($transfer);

    expect($transfer->withdraw->refresh()->confirmed)->toBeFalse()
        ->and($transfer->deposit->refresh()->confirmed)->toBeFalse()
        ->and((float) $user->balanceFloat)->toBe(400.0)
        ->and((float) $vendor->balanceFloat)->toBe(0.0);
});
