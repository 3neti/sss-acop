<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Commerce\Models\{Product, Vendor};

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Commerce\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word,
            'description' => fake()->sentence,
            'price' => fake()->numberBetween( 50, 5000),
            'currency' => 'PHP',
            'vendor_id' => Vendor::factory(),
        ];
    }
}
