<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\KYC\Models\Identification;
use App\KYC\Enums\KYCIdType;

/**
 * Factory for generating Identification model instances.
 *
 * This factory supports generating different types of user identifiers
 * such as email, mobile numbers, or government-issued IDs for use in
 * tests or database seeding.
 *
 * ## Usage Examples:
 *
 * Create a default random identification:
 * ```php
 * Identification::factory()->create();
 * ```
 *
 * Create an identification for a specific email:
 * ```php
 * Identification::factory()->email('johndoe@example.com')->create();
 * ```
 *
 * Attach identifications to a user:
 * ```php
 * User::factory()
 *     ->has(Identification::factory()->email())
 *     ->has(Identification::factory()->mobile())
 *     ->create();
 * ```
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class IdentificationFactory extends Factory
{
    protected $model = Identification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_type' => KYCIdType::random(),
            'id_value' => $this->faker->uuid(),
            'meta' => [],
        ];
    }

    /**
     * Create an email-type identification.
     *
     * @param string|null $email The email address. If null, a random one is generated.
     * @return static
     */
    public function email(?string $email = null): static
    {
        return $this->state([
            'id_type' => KYCIdType::EMAIL,
            'id_value' => $email ?? $this->faker->safeEmail,
        ]);
    }

    /**
     * Create a mobile-type identification.
     *
     * @param string|null $number The mobile number. If null, a random one is generated.
     * @return static
     */
    public function mobile(?string $number = null): static
    {
        return $this->state([
            'id_type' => KYCIdType::MOBILE,
            'id_value' => $number ?? $this->faker->phoneNumber,
        ]);
    }
}
