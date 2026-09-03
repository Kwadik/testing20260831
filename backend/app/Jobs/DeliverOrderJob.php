<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DeliverOrderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $orderId,
    ) {
    }

    public function handle(
        DeliveryService $deliveryService,
    ): void {
        $order = Order::query()
            ->find($this->orderId);

        if ($order === null) {
            return;
        }

        /*
         * A job can be queued more than once.
         *
         * If another delivery attempt has already completed the order,
         * there is nothing left to do.
         */
        if ($order->status === OrderStatus::DELIVERED) {
            return;
        }

        /*
         * DeliveryService itself is responsible for:
         *
         * - idempotency;
         * - provider request_id;
         * - concurrency;
         * - timeout handling;
         * - out_of_stock;
         * - delivery_failed.
         */
        $requestId = 'delivery-' . $order->public_id;

        try {
            $deliveryService->deliver(
                $order,
                $requestId,
            );
        } catch (\Throwable $e) {
            /*
             * Delivery failures are recoverable states and must not
             * cause the payment webhook itself to fail.
             *
             * The order/attempt state has already been recorded by
             * DeliveryService.
             */
            Log::warning('Order delivery failed.', [
                'order_id' => $order->id,
                'public_id' => $order->public_id,
                'request_id' => $requestId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
