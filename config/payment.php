<?php

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'credit_card'),
    
    'credit_card' => [
        'api_key' => env('CREDIT_CARD_API_KEY'),
        'api_secret' => env('CREDIT_CARD_API_SECRET'),
        'environment' => env('CREDIT_CARD_ENVIRONMENT', 'sandbox'),
    ],
    
    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'environment' => env('PAYPAL_ENVIRONMENT', 'sandbox'),
    ],
    
    'stripe' => [
        'api_key' => env('STRIPE_API_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'environment' => env('STRIPE_ENVIRONMENT', 'sandbox'),
    ],
];