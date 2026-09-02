<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$provider = app(\App\Services\Delivery\ProviderA::class);

$result = $provider->issue(
    requestId: $argv[1],
    sku: $argv[2],
    orderId: $argv[3],
);

echo json_encode([
    'status' => $result->status,
    'code' => $result->code,
    'reason' => $result->reason,
]);