<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Commerce\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_id' => 'REF-' . Str::upper(Str::random(6)),
            'meta' => [
                'item' => $this->faker->words(3, true),
                'vendor' => $this->faker->company,
            ],
            'amount' => $this->faker->randomFloat(2, 50, 1000),
            'currency' => 'PHP',
            'callback_url' => $this->faker->url(),
        ];
    }
}
