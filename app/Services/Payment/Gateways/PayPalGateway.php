<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;

class PayPalGateway implements PaymentGatewayInterface
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            'client_id' => config('payment.paypal.client_id'),
            'client_secret' => config('payment.paypal.client_secret'),
            'environment' => config('payment.paypal.environment', 'sandbox'),
        ];
    }

    public function getName(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal';
    }

    public function processPayment(float $amount, array $data = []): array
    {
        // Simulate PayPal payment processing
        $paypalToken = $data['paypal_token'] ?? '';

        if (empty($paypalToken)) {
            throw new \InvalidArgumentException('PayPal token is required');
        }

        // Simulate API call to PayPal
        $success = $this->simulatePayPalPayment($amount);

        if ($success) {
            $transactionId = 'PP-' . strtoupper(uniqid());
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'message' => 'PayPal payment processed successfully',
                'gateway_response' => [
                    'status' => 'COMPLETED',
                    'paypal_order_id' => $transactionId,
                    'payer_email' => $data['payer_email'] ?? 'customer@example.com',
                    'transaction_time' => now()->toISOString(),
                ]
            ];
        }

        return [
            'success' => false,
            'error' => 'PayPal payment failed',
            'gateway_response' => [
                'status' => 'FAILED',
                'reason' => 'Payment authorization failed',
            ]
        ];
    }

    public function refund(string $transactionId, float $amount = null): array
    {
        // Simulate PayPal refund
        return [
            'success' => true,
            'refund_id' => 'PPREF-' . strtoupper(uniqid()),
            'transaction_id' => $transactionId,
            'amount_refunded' => $amount,
            'message' => 'PayPal refund processed successfully',
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD'];
    }

    public function validateCredentials(): bool
    {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }

    private function simulatePayPalPayment(float $amount): bool
    {
        // Simulate PayPal payment success (95% success rate for demo)
        return rand(1, 100) <= 95;
    }
}