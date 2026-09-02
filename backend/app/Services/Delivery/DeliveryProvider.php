<?php

namespace App\Services\Delivery;

interface DeliveryProvider
{
    public function issue(
        string $requestId,
        string $sku,
        string $orderId,
    ): ProviderIssueResult;
}
