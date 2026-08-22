<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Balance Top-up Providers
    |--------------------------------------------------------------------------
    | Providers available for balance top-up. Must be registered in
    | PaymentGatewayResolver. Keys are provider codes.
    */
    'enabled_providers' => ['heleket', 'cryptomus'],

    /*
    |--------------------------------------------------------------------------
    | Payment Method Labels & Notes
    |--------------------------------------------------------------------------
    */
    'methods' => [
        'heleket' => [
            'code'  => 'heleket',
            'title' => 'Cryptocurrency (Heleket)',
            'notes' => 'Pay with crypto via Heleket gateway.',
        ],
        'cryptomus' => [
            'code'  => 'cryptomus',
            'title' => 'Cryptocurrency (Cryptomus)',
            'notes' => 'Pay with crypto via Cryptomus gateway.',
        ],
    ],
];
