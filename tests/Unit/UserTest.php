<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\KYC\Enums\KYCIdType;
use App\Models\User;

uses(RefreshDatabase::class);

dataset('bridgeAttributes', [
    ['pin', KYCIdType::PIN, '123456', '999999'],
//    ['email', KYCIdType::EMAIL, 'john@example.com', 'jane@example.com'],
//    ['mobile', KYCIdType::MOBILE, '09171234567', '09998887777'],
]);

test('user can get and set bridge attributes via identification model', function (
    string $attribute,
    KYCIdType $type,
    string $initialValue,
    string $updatedValue
) {
    $user = User::factory()->create();

    // Initially null
    expect($user->{$attribute})->toBeNull();

    // Set value
    $user->{$attribute} = $initialValue;
    $user->save();

    // Refresh to ensure persisted
    $user->refresh();
    expect($user->{$attribute})->toBe($initialValue);

    // Confirm in DB
    $this->assertDatabaseHas('identifications', [
        'user_id' => $user->id,
        'id_type' => $type->value,
        'id_value' => $initialValue,
    ]);

    // Update value
    $user->{$attribute} = $updatedValue;
    $user->save();

    $user->refresh();
    expect($user->{$attribute})->toBe($updatedValue);

    // Ensure old value is gone
    $this->assertDatabaseMissing('identifications', [
        'user_id' => $user->id,
        'id_type' => $type->value,
        'id_value' => $initialValue,
    ]);
})->with('bridgeAttributes');
