<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $token;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();
        
        // إنشاء مستخدم وتسجيل الدخول
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
        
        // الحصول على التوكن
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('authorization.token');
        
        // إنشاء طلب مؤكد
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
            'status' => 'confirmed',
        ]);
    }

    public function test_get_available_payment_gateways()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/payments/gateways');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['name', 'display_name', 'currencies']
                ]
            ]);
    }

    public function test_process_payment()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments/process', [
            'order_id' => $this->order->id,
            'gateway' => 'credit_card',
            'payment_method' => 'visa',
            'payment_data' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '12/25',
                'cvv' => '123',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'payment_number',
                    'amount',
                    'status',
                    'gateway_name'
                ]
            ]);
    }

    public function test_cannot_process_payment_for_pending_order()
    {
        // إنشاء طلب غير مؤكد
        $pendingOrder = Order::create([
            'user_id' => $this->user->id,
            'order_number' => 'ORD-PENDING',
            'customer_name' => 'Test Customer',
            'customer_email' => 'customer@test.com',
            'customer_address' => 'Test Address',
            'customer_phone' => '1234567890',
            'subtotal' => 50.00,
            'tax' => 5.00,
            'shipping' => 5.00,
            'total' => 60.00,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments/process', [
            'order_id' => $pendingOrder->id,
            'gateway' => 'credit_card',
            'payment_method' => 'visa',
            'payment_data' => [
                'card_number' => '4111111111111111',
                'expiry_date' => '12/25',
                'cvv' => '123',
            ],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error'
            ]);
    }

    public function test_get_payments_list()
    {
        // إنشاء دفع أولاً
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
        ])->getJson('/api/payments');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta',
                'links'
            ]);
    }

    public function test_get_single_payment()
    {
        $payment = Payment::create([
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
        ])->getJson('/api/payments/' . $payment->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'payment_number',
                    'amount',
                    'status'
                ]
            ]);
    }

    public function test_refund_payment()
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'payment_number' => 'PAY-REFUNDTEST',
            'amount' => 115.00,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
            'gateway_name' => 'credit_card',
            'gateway_reference' => 'TXN-REFUND123',
            'status' => 'successful',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments/' . $payment->id . '/refund', [
            'amount' => 115.00,
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'refund',
                    'payment'
                ]
            ]);
    }

    public function test_cannot_refund_non_successful_payment()
    {
        $payment = Payment::create([
            'order_id' => $this->order->id,
            'payment_number' => 'PAY-FAILED',
            'amount' => 115.00,
            'currency' => 'USD',
            'payment_method' => 'credit_card',
            'gateway_name' => 'credit_card',
            'gateway_reference' => 'TXN-FAILED123',
            'status' => 'failed',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/payments/' . $payment->id . '/refund', [
            'amount' => 115.00,
            'reason' => 'Customer request',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 'error',
                'message' => 'Only successful payments can be refunded'
            ]);
    }
}