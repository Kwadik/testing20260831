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
];
