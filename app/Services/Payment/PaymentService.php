<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private PaymentGatewayFactory $factory;

    public function __construct(PaymentGatewayFactory $factory)
    {
        $this->factory = $factory;
    }

    public function processOrderPayment(Order $order, string $gatewayName, array $paymentData): Payment
    {
        // Check if order is confirmed
        if (!$order->isConfirmed()) {
            throw new PaymentGatewayException('Order must be confirmed before processing payment.');
        }

        return DB::transaction(function () use ($order, $gatewayName, $paymentData) {
            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total,
                'currency' => 'USD',
                'payment_method' => $paymentData['payment_method'] ?? $gatewayName,
                'gateway_name' => $gatewayName,
                'status' => 'pending',
            ]);

            try {
                // Get gateway instance
                $gateway = $this->factory->make($gatewayName);
                
                // Process payment through gateway
                $result = $gateway->processPayment($order->total, $paymentData);

                if ($result['success']) {
                    $payment->markAsSuccessful(
                        $result['transaction_id'],
                        $result['gateway_response'] ?? []
                    );
                } else {
                    $payment->markAsFailed($result['gateway_response'] ?? []);
                    throw new PaymentGatewayException(
                        $result['error'] ?? 'Payment processing failed',
                        0,
                        $result
                    );
                }

                return $payment->fresh();

            } catch (\Exception $e) {
                // If payment failed but exception was thrown before marking as failed
                if ($payment->status === 'pending') {
                    $payment->markAsFailed([
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]);
                }
                
                throw new PaymentGatewayException(
                    "Payment processing failed: {$e->getMessage()}",
                    $e->getCode(),
                    $e
                );
            }
        });
    }

    public function getAvailableGateways(): array
    {
        return $this->factory->getAvailableGateways();
    }

    // إضافة هذه الدالة - كانت مفقودة
    public function make(string $gatewayName): PaymentGatewayInterface
    {
        return $this->factory->make($gatewayName);
    }

    public function registerGateway(string $name, string $gatewayClass): void
    {
        $this->factory->registerGateway($name, $gatewayClass);
    }
}