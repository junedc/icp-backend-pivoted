<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{

    public function __construct(private OrderService $orderService)
    {
    }

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

        $items = ($request->validated('items'));

        $order = $this->orderService->place($request->user()->id, $items);

        return response()->json($order, 201);


    }

    public function complete(Request $request, Order $order)
    {
        $this->ensureOwner($request, $order);

        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending orders can be completed.'],
            ]);
        }

        $order = $this->orderService->complete($order);
        $order->load(['products:id,name,sku,price,stock_quantity']);
        return response()->json($order);
    }

    public function cancel(Request $request, Order $order)
    {
        $this->ensureOwner($request, $order);

        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending orders can be cancelled.'],
            ]);
        }

        $order = $this->orderService->cancel($order);
        $order->load(['products:id,name,sku,price,stock_quantity']);
        return response()->json($order);


    }

    private function ensureOwner(Request $request, Order $order): void
    {
        if ($order->user_id !== $request->user()->id) {
            abort(403, 'You do not have access to this order.');
        }
    }
}
