<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Concurrency test delay
    |--------------------------------------------------------------------------
    |
    | Artificial delay used only by concurrency tests to make race
    | conditions reproducible.
    |
    */

    'concurrency_test_delay_ms' => (int) env(
        'DELIVERY_CONCURRENCY_TEST_DELAY_MS',
        0
    ),

    'provider_a' => [
        'mode' => env(
            'DELIVERY_PROVIDER_A_MODE',
            'normal'
        ),
    ],

    'provider_b' => [
        'mode' => env(
            'DELIVERY_PROVIDER_B_MODE',
            'normal'
        ),
    ],
];
