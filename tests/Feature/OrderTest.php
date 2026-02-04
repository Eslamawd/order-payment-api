<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create User 
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
        
        // Get Token
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('authorization.token');
        
        // Create Order 
        $this->order = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-TEST123',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
            'customer_address' => 'Test Address',
            'customer_phone' => '1234567890',
            'subtotal' => 100.00,
            'tax' => 10.00,
            'shipping' => 5.00,
            'total' => 115.00,
            'status' => 'pending',
        ]);
        
        // Create Item 
        OrderItems::create([
            'order_id' => $this->order->id,
            'product_name' => 'Test Product',
            'quantity' => 1,
            'price' => 100.00,
            'total' => 100.00,
        ]);
    }

    public function test_create_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/orders', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_address' => '123 Main St',
            'customer_phone' => '1234567890',
            'items' => [
                [
                    'product_name' => 'Product 1',
                    'quantity' => 2,
                    'price' => 25.50,
                ],
                [
                    'product_name' => 'Product 2',
                    'quantity' => 1,
                    'price' => 49.99,
                ]
            ],
            'tax' => 10.00,
            'shipping' => 5.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'customer_name',
                    'total',
                    'items'
                ]
            ]);
    }

    public function test_get_orders()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'links'
            ]);
    }

    public function test_get_single_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/orders/' . $this->order->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'order_number',
                    'customer_name',
                    'total',
                    'items'
                ]
            ]);
    }

    public function test_update_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/orders/' . $this->order->id, [
            'customer_name' => 'Updated Customer',
            'customer_email' => 'updated@test.com',
            'customer_address' => 'Updated Address',
            'customer_phone' => '9876543210',
            'notes' => 'Updated notes for testing',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data'
            ])
            ->assertJson([
                'status' => 'success',
                'message' => 'Order updated successfully'
            ]);
    }

    public function test_cannot_update_order_with_payments()
    {
        // إنشاء دفع للطلب
        Payment::create([
            'order_id' => $this->order->id,
            'payment_number' => 'PAY-TEST123',
            'amount' => 115.00,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
            'gateway_name' => 'credit_card',
            'gateway_reference' => 'TXN-123456',
            'status' => 'successful',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/orders/' . $this->order->id, [
            'customer_name' => 'Try Update',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot update order with existing payments'
            ]);
    }

    public function test_delete_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/orders/' . $this->order->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Order deleted successfully'
            ]);
    }

    public function test_cannot_delete_order_with_payments()
    {
        // payment this order
        Payment::create([
            'order_id' => $this->order->id,
            'payment_number' => 'PAY-TEST456',
            'amount' => 115.00,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
            'gateway_name' => 'credit_card',
            'gateway_reference' => 'TXN-789012',
            'status' => 'successful',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson('/api/orders/' . $this->order->id);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Cannot delete order with associated payments'
            ]);
    }

    public function test_confirm_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/orders/' . $this->order->id . '/confirm');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Order confirmed successfully'
            ]);
    }

    public function test_cannot_confirm_already_confirmed_order()
    {
        // تحديث حالة الطلب إلى confirmed
        $this->order->update(['status' => 'confirmed']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/orders/' . $this->order->id . '/confirm');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Order is not in pending status'
            ]);
    }

    public function test_cancel_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/orders/' . $this->order->id . '/cancel');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Order cancelled successfully'
            ]);
    }

    public function test_cannot_cancel_already_cancelled_order()
    {
        // تحديث حالة الطلب إلى cancelled
        $this->order->update(['status' => 'cancelled']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/orders/' . $this->order->id . '/cancel');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Order is already cancelled'
            ]);
    }

    public function test_filter_orders_by_status()
    {
        // إنشاء طلب إضافي بحالة confirmed
        Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-CONFIRMED',
            'customer_name' => 'Confirmed Customer',
            'customer_email' => 'confirmed@test.com',
            'customer_address' => 'Confirmed Address',
            'customer_phone' => '1111111111',
            'subtotal' => 50.00,
            'tax' => 5.00,
            'shipping' => 5.00,
            'total' => 60.00,
            'status' => 'confirmed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/orders?status=confirmed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'links'
            ]);
        
        // تأكد من أن جميع النتائج بحالة confirmed
        $orders = $response->json('data');
        foreach ($orders as $order) {
            $this->assertEquals('confirmed', $order['status']);
        }
    }

    public function test_search_orders()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/orders?search=Test Customer');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'links'
            ]);
    }

    public function test_validation_on_create_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/orders', [
            // بيانات غير صحيحة
            'customer_name' => '',
            'customer_email' => 'invalid-email',
            'items' => []
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'errors'
            ]);
    }

    public function test_validation_on_update_order()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/orders/' . $this->order->id, [
            'customer_email' => 'invalid-email',
            'status' => 'invalid-status'
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'status',
                'errors'
            ]);
    }
}