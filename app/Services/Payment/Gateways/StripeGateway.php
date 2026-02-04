<?php

namespace App\Services\Payment\Gateways;

use App\Services\Payment\Contracts\PaymentGatewayInterface;

class StripeGateway implements PaymentGatewayInterface
{
    public function getName(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function processPayment(float $amount, array $data = []): array
    {
        $stripeToken = $data['stripe_token'] ?? '';
        $cardNumber = $data['card_number'] ?? '';

        if (empty($stripeToken) && empty($cardNumber)) {
            throw new \InvalidArgumentException('Stripe token or card number is required');
        }

        // محاكاة دفع Stripe
        $success = rand(1, 100) <= 98; // 98% نسبة نجاح

        if ($success) {
            $transactionId = 'STRIPE-' . strtoupper(uniqid());
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $data['currency'] ?? 'USD',
                'message' => 'Stripe payment processed successfully',
                'gateway_response' => [
                    'status' => 'succeeded',
                    'stripe_charge_id' => $transactionId,
                    'payment_method' => $data['payment_method'] ?? 'card',
                    'transaction_time' => now()->toISOString(),
                ]
            ];
        }

        return [
            'success' => false,
            'error' => 'Stripe payment failed',
            'gateway_response' => [
                'status' => 'failed',
                'reason' => 'Card declined',
            ]
        ];
    }

    public function refund(string $transactionId, float $amount = null): array
    {
        return [
            'success' => true,
            'refund_id' => 'STRREF-' . strtoupper(uniqid()),
            'transaction_id' => $transactionId,
            'amount_refunded' => $amount,
            'message' => 'Stripe refund processed successfully',
        ];
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF'];
    }

    public function validateCredentials(): bool
    {
        return !empty(config('payment.stripe.api_key'));
    }
}