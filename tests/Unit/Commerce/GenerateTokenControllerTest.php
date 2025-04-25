<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Laravel\postJson;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

test('user can generate a personal access token', function () {
    $response = postJson(route('profile.token.generate'), [
        'tokenName' => 'Vendor API',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['success', 'token']);

    $this->assertTrue($this->user->tokens()->where('name', 'Vendor API')->exists());
});

test('token name is required', function () {
    $response = postJson(route('profile.token.generate'), [
        'tokenName' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['tokenName']);
});

test('token name must be a string and max 255 characters', function () {
    $response = postJson(route('profile.token.generate'), [
        'tokenName' => str_repeat('x', 256),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['tokenName']);
});
