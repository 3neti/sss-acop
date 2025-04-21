<?php

use function Pest\Laravel\assertDatabaseHas;
use App\Commerce\Events\WalletToppedUp;
use Illuminate\Support\Facades\Event;
use App\Commerce\Actions\TopupWallet;
use App\Commerce\Models\Transfer;
use App\Commerce\Models\System;
use Brick\Money\Money;
use App\Models\User;

beforeEach(function () {
    $this->system = System::factory()->create();
    $this->system->depositFloat(10000000);
});

it('tops up a user wallet from the system user and dispatches event', function () {
    Event::fake();

    $user = User::factory()->create();
    expect((float) $user->balanceFloat)->toBe(0.0);
    $amount = Money::of(5000, 'PHP');

    $transfer = TopupWallet::run($user, $amount, [
        'reason' => 'test funding',
        'initiated_by' => 'unit_test',
    ]);

    expect($transfer)->toBeInstanceOf(Transfer::class)
        ->and($transfer->deposit->wallet->holder_id)->toBe($user->id)
        ->and($transfer->withdraw->wallet->holder_id)->toBe($this->system->id)
        ->and((float) $user->balanceFloat)->toBe(5000.0);

    assertDatabaseHas('transfers', [
        'id' => $transfer->id,
        'deposit_id' => $transfer->deposit->id,
        'withdraw_id' => $transfer->withdraw->id,
    ]);

    Event::assertDispatched(WalletToppedUp::class, function ($event) use ($user, $amount, $transfer) {
        return $event->user->is($user)
            && $event->amount->isEqualTo($amount)
            && $event->transfer->is($transfer)
            && $event->meta['reason'] === 'test funding';
    });
});

it('throws if amount is zero or less', function () {
    $user = User::factory()->create();
    TopupWallet::run($user, 0);
})->throws(InvalidArgumentException::class);

it('throws if non-numeric string is passed', function () {
    $user = User::factory()->create();
    TopupWallet::run($user, 'invalid');
})->throws(InvalidArgumentException::class);
