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
    'enabled_providers' => array_merge(
        ['heleket', 'cryptomus'],
        // Shown only once the Baosbabs API key is configured
        env('BAOSBABS_API_KEY') ? ['baosbabs_usdt_trc20', 'baosbabs_usdt_bep20', 'baosbabs_trx'] : [],
    ),

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
        'baosbabs_usdt_trc20' => [
            'code'  => 'baosbabs_usdt_trc20',
            'title' => 'USDT (TRC-20)',
            'notes' => 'Pay with USDT on the Tron network.',
        ],
        'baosbabs_usdt_bep20' => [
            'code'  => 'baosbabs_usdt_bep20',
            'title' => 'USDT (BEP-20)',
            'notes' => 'Pay with USDT on BNB Smart Chain.',
        ],
        'baosbabs_trx' => [
            'code'  => 'baosbabs_trx',
            'title' => 'TRX',
            'notes' => 'Pay with TRX on the Tron network.',
        ],
    ],
];
