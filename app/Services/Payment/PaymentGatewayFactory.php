<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\CreditCardGateway;
use App\Services\Payment\Gateways\PayPalGateway;
use App\Services\Payment\Gateways\StripeGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    // Can add New Gateways Fawry 
    private array $gateways = [
        'credit_card' => CreditCardGateway::class,
        'paypal' => PayPalGateway::class,
        'stripe' => StripeGateway::class,
    ];

    public function make(string $gatewayName): PaymentGatewayInterface
    {
        if (!isset($this->gateways[$gatewayName])) {
            throw new InvalidArgumentException("Payment gateway [{$gatewayName}] is not supported.");
        }

        $gatewayClass = $this->gateways[$gatewayName];
        
        return app($gatewayClass);
    }

    public function getAvailableGateways(): array
    {
        $available = [];

        foreach ($this->gateways as $name => $class) {
            $gateway = app($class);
            if ($gateway->validateCredentials()) {
                $available[] = [
                    'name' => $gateway->getName(),
                    'display_name' => $gateway->getDisplayName(),
                    'currencies' => $gateway->getSupportedCurrencies(),
                ];
            }
        }

        return $available;
    }

    public function registerGateway(string $name, string $gatewayClass): void
    {
        if (!is_subclass_of($gatewayClass, PaymentGatewayInterface::class)) {
            throw new InvalidArgumentException("Gateway must implement PaymentGatewayInterface");
        }

        $this->gateways[$name] = $gatewayClass;
    }
}