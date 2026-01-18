<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => Order::STATUS_PENDING,
        ];
    }

    public function withProducts(int $count = 2, bool $deductStock = true): static
    {
        return $this->afterCreating(function (Order $order) use ($count, $deductStock) {
            DB::transaction(function () use ($order, $count, $deductStock) {
                $products = Product::query()
                    ->where('stock_quantity', '>', 0)
                    ->inRandomOrder()
                    ->take($count)
                    ->lockForUpdate()
                    ->get();

                if ($products->count() < $count) {
                    $products = Product::factory()
                        ->count($count)
                        ->inStock()
                        ->create();
                }

                foreach ($products as $product) {
                    $quantity = rand(1, min(3, (int) $product->stock_quantity));

                    $order->products()->attach($product->id, [
                        'quantity' => $quantity,
                        'unit_price' => $product->price,
                    ]);

                    if ($deductStock) {
                        $product->decrement('stock_quantity', $quantity);
                    }
                }
            });
        });
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_COMPLETED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_CANCELLED,
        ]);
    }
}
