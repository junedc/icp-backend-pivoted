<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    public function test_user_can_create_order_and_stock_is_deducted_and_pivot_is_created(): void
    {
        $this->signIn();

        $p1 = Product::factory()->inStock(10, 10)->create(['price' => 50.00]);
        $p2 = Product::factory()->inStock(3, 3)->create(['price' => 25.50]);

        $payload = [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
                ['product_id' => $p2->id, 'quantity' => 1],
            ],
        ];

        $res = $this->postJson('/api/orders', $payload);

        $res->assertCreated()
            ->assertJsonFragment(['status' => Order::STATUS_PENDING]);

        $orderId = $res->json('id');

        $this->assertDatabaseHas('products', [
            'id' => $p1->id,
            'stock_quantity' => 8,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $p2->id,
            'stock_quantity' => 2,
        ]);

        $this->assertDatabaseHas('order_product', [
            'order_id' => $orderId,
            'product_id' => $p1->id,
            'quantity' => 2,
            'unit_price' => '50.00',
        ]);
        $this->assertDatabaseHas('order_product', [
            'order_id' => $orderId,
            'product_id' => $p2->id,
            'quantity' => 1,
            'unit_price' => '25.50',
        ]);
    }

    public function test_create_order_fails_when_insufficient_stock(): void
    {
        $this->signIn();

        $p1 = Product::factory()->inStock(1, 1)->create(['price' => 10.00]);

        $payload = [
            'items' => [
                ['product_id' => $p1->id, 'quantity' => 2],
            ],
        ];

        $res = $this->postJson('/api/orders', $payload);

        $res->assertStatus(422);

        $this->assertDatabaseHas('products', [
            'id' => $p1->id,
            'stock_quantity' => 1,
        ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_product', 0);
    }

    public function test_user_can_cancel_pending_order_and_stock_is_restocked(): void
    {
        $user = $this->signIn();

        $p1 = Product::factory()->create([
            'stock_quantity' => 10,
            'price' => 99.99,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
        ]);

        $order->products()->attach($p1->id, [
            'quantity' => 3,
            'unit_price' => $p1->price,
        ]);
        $p1->decrement('stock_quantity', 3);

        $this->assertDatabaseHas('products', [
            'id' => $p1->id,
            'stock_quantity' => 7,
        ]);

        $res = $this->patchJson("/api/orders/{$order->id}/cancel");

        $res->assertOk()
            ->assertJsonFragment(['status' => Order::STATUS_CANCELLED]);

        $this->assertDatabaseHas('products', [
            'id' => $p1->id,
            'stock_quantity' => 10,
        ]);
    }

    public function test_user_can_complete_pending_order(): void
    {
        $user = $this->signIn();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
        ]);

        $res = $this->patchJson("/api/orders/{$order->id}/complete");

        $res->assertOk()
            ->assertJsonFragment(['status' => Order::STATUS_COMPLETED]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_COMPLETED,
        ]);
    }

    public function test_cannot_complete_or_cancel_non_pending_order(): void
    {
        $user = $this->signIn();

        $completed = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_COMPLETED,
        ]);

        $this->patchJson("/api/orders/{$completed->id}/cancel")->assertStatus(422);
        $this->patchJson("/api/orders/{$completed->id}/complete")->assertStatus(422);

        $cancelled = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_CANCELLED,
        ]);

        $this->patchJson("/api/orders/{$cancelled->id}/cancel")->assertStatus(422);
        $this->patchJson("/api/orders/{$cancelled->id}/complete")->assertStatus(422);
    }

    public function test_user_cannot_view_or_modify_other_users_order(): void
    {
        $owner = User::factory()->create();
        $this->signIn(); // logged in as different user

        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'status' => Order::STATUS_PENDING,
        ]);

        $this->getJson("/api/orders/{$order->id}")->assertStatus(403);
        $this->patchJson("/api/orders/{$order->id}/cancel")->assertStatus(403);
        $this->patchJson("/api/orders/{$order->id}/complete")->assertStatus(403);
    }
}
