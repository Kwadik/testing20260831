<?php

namespace App\Services\Delivery;

use App\Exceptions\ProviderTimeoutException;

readonly class DeliveryOrchestrator
{
    public function __construct(
        private DeliveryProvider $providerA,
        private DeliveryProvider $providerB,
    ) {
    }

    public function issue(
        string $requestId,
        string $sku,
        string $orderId,
    ): ProviderIssueResult {
        try {
            $result = $this->providerA->issue(
                requestId: $requestId,
                sku: $sku,
                orderId: $orderId,
            );
        } catch (ProviderTimeoutException $e) {
            throw $e;
        }

        if ($result->status === 'ok') {
            return $result;
        }

        return $this->providerB->issue(
            requestId: $requestId,
            sku: $sku,
            orderId: $orderId,
        );
    }
}
