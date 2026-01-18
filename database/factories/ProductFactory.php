<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-#####')),
            'price' => $this->faker->randomFloat(2, 5, 500),
            'stock_quantity' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inStock(int $min = 1, int $max = 50): static
    {
        return $this->state(fn () => [
            'stock_quantity' => $this->faker->numberBetween($min, $max),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn () => [
            'stock_quantity' => 0,
        ]);
    }
}
