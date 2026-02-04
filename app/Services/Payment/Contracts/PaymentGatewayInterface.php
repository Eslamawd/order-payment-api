<?php

namespace App\Services\Payment\Contracts;

interface PaymentGatewayInterface
{
    public function getName(): string;
    public function getDisplayName(): string;
    public function processPayment(float $amount, array $data = []): array;
    public function refund(string $transactionId, float $amount = null): array;
    public function getSupportedCurrencies(): array;
    public function validateCredentials(): bool;
}