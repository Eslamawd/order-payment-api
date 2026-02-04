<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;

class CreditCardGateway implements PaymentGatewayInterface
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            'api_key' => config('payment.credit_card.api_key'),
            'api_secret' => config('payment.credit_card.api_secret'),
            'environment' => config('payment.credit_card.environment', 'sandbox'),
        ];
    }

    public function getName(): string
    {
        return 'credit_card';
    }

    public function getDisplayName(): string
    {
        return 'Credit Card';
    }

    public function processPayment(float $amount, array $data = []): array
    {
        // Simulate payment processing
        $cardNumber = $data['card_number'] ?? '';
        $expiry = $data['expiry_date'] ?? '';
        $cvv = $data['cvv'] ?? '';

        // Validate card details (simplified)
        if (empty($cardNumber) || empty($expiry) || empty($cvv)) {
            throw new \InvalidArgumentException('Invalid card details');
        }

        // Simulate API call to payment processor
        $success = $this->simulatePayment($amount);

        if ($success) {
            $transactionId = 'CC-' . strtoupper(uniqid());
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'message' => 'Payment processed successfully',
                'gateway_response' => [
                    'status' => 'approved',
                    'auth_code' => strtoupper(bin2hex(random_bytes(6))),
                    'transaction_time' => now()->toISOString(),
                ]
            ];
        }

        return [
            'success' => false,
            'error' => 'Payment declined by bank',
            'gateway_response' => [
                'status' => 'declined',
                'reason' => 'Insufficient funds',
            ]
        ];
    }

    public function refund(string $transactionId, float $amount = null): array
    {
        // Simulate refund process
        return [
            'success' => true,
            'refund_id' => 'REF-' . strtoupper(uniqid()),
            'transaction_id' => $transactionId,
            'amount_refunded' => $amount,
            'message' => 'Refund processed successfully',
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
    }

    public function validateCredentials(): bool
    {
        return !empty($this->config['api_key']) && !empty($this->config['api_secret']);
    }

    private function simulatePayment(float $amount): bool
    {
        // Simulate payment success (90% success rate for demo)
        return rand(1, 10) <= 9;
    }
}