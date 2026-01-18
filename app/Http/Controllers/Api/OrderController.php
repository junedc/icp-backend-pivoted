<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['products:id,name,sku,price,stock_quantity'])
            ->latest()
            ->paginate(10);
    }

    public function show(Request $request, Order $order)
    {
        $this->ensureOwner($request, $order);
        return $order->load(['products:id,name,sku,price,stock_quantity']);
    }

    public function store(StoreOrderRequest $request)
    {
        $user = $request->user();
        $items = collect($request->validated('items'));

        return DB::transaction(function () use ($user, $items) {
            $productIds = $items->pluck('product_id')->unique()->values();

            $gstRate = 0.10;  //TODO: add in env later on
            $subtotal = 0;

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products->get($item['product_id']);
                $subtotal += $product->price * $item['quantity'];

                if (!$product) {
                    throw ValidationException::withMessages([
                        'items' => ["Product {$item['product_id']} not found."],
                    ]);
                }

                if ($product->stock_quantity < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for SKU {$product->sku}."],
                    ]);
                }
            }

            $gstAmount = round($subtotal * $gstRate, 2);
            $total = $subtotal + $gstAmount;

            $order = Order::create([
                'user_id' => $user->id,
                'status' => Order::STATUS_PENDING,
                'subtotal' => $subtotal,
                'gst_rate' => $gstRate,
                'gst_amount' => $gstAmount,
                'total' => $total,
            ]);

            $pivotData = [];
            foreach ($items as $item) {
                $product = $products->get($item['product_id']);

                $pivotData[$product->id] = [
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $product->price,
                ];

                $product->decrement('stock_quantity', (int) $item['quantity']);
            }

            $order->products()->attach($pivotData);

            return response()->json(
                $order->load(['products:id,name,sku,price,stock_quantity']),
                201
            );
        });
    }

    public function complete(Request $request, Order $order)
    {
        $this->ensureOwner($request, $order);

        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending orders can be completed.'],
            ]);
        }

        $order->update(['status' => Order::STATUS_COMPLETED]);

        return $order->load(['products:id,name,sku,price,stock_quantity']);
    }

    public function cancel(Request $request, Order $order)
    {
        $this->ensureOwner($request, $order);

        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending orders can be cancelled.'],
            ]);
        }

        return DB::transaction(function () use ($order) {
            $order->load('products');

            $productIds = $order->products->pluck('id')->values();

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->products as $p) {
                $qty = (int) $p->pivot->quantity;
                $products->get($p->id)?->increment('stock_quantity', $qty);
            }

            $order->update(['status' => Order::STATUS_CANCELLED]);

            return $order->load(['products:id,name,sku,price,stock_quantity']);
        });
    }

    private function ensureOwner(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this order.');
        }
    }
}
