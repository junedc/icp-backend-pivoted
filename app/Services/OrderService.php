<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function place(int $userId, array $items, float $gstRate = 0.10): Order
    {
        return DB::transaction(function () use ($userId, $items, $gstRate) {
            // Load products once
            $productIds = collect($items)->pluck('product_id')->unique()->values();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate() // avoid race conditions on stock
                ->get()
                ->keyBy('id');

            // Validate + calculate subtotal
            $subtotal = 0;

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                if (!$product) {
                    throw ValidationException::withMessages([
                        'items' => ["Product {$item['product_id']} not found."],
                    ]);
                }

                $qty = (int)$item['quantity'];
                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        'items' => ["Quantity must be at least 1 for {$product->name}."],
                    ]);
                }

                if ($product->stock_quantity < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Not enough stock for {$product->name}."],
                    ]);
                }

                $subtotal += ((float)$product->price) * $qty;
            }

            // Create order (include GST fields if you added them)
            $gstAmount = round($subtotal * $gstRate, 2);
            $total = round($subtotal + $gstAmount, 2);

            $order = Order::create([
                'user_id' => $userId,
                'status' => Order::STATUS_PENDING, // define constants on model
                'subtotal' => $subtotal,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'total' => $total,
            ]);

            // Attach items + decrement stock
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $qty = (int)$item['quantity'];

                $order->products()->attach($product->id, [
                    'quantity' => $qty,
                    'unit_price' => $product->price,
                ]);

                $product->decrement('stock_quantity', $qty);
            }

            return $order->load('products');
        });
    }

    public function cancel(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            if ($order->status !== Order::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'order' => ['Only pending orders can be cancelled.'],
                ]);
            }

            $order->load('products');

            // Restock
            foreach ($order->products as $p) {
                $qty = (int)$p->pivot->quantity;
                Product::whereKey($p->id)->increment('stock_quantity', $qty);
            }

            $order->update(['status' => Order::STATUS_CANCELLED]);

            return $order;
        });
    }

    public function complete(Order $order): Order
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'order' => ['Only pending orders can be completed.'],
            ]);
        }

        $order->update(['status' => Order::STATUS_COMPLETED]);

        return $order;
    }
}
