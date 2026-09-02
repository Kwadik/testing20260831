<?php

namespace App\Services;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\OrderStatus;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\OrderNotReadyException;
use App\Exceptions\OutOfStockException;
use App\Exceptions\ProviderTimeoutException;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Services\Delivery\DeliveryOrchestrator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function __construct(
        private readonly DeliveryOrchestrator $orchestrator,
    ) {
    }

    public function deliver(
        Order $order,
        string $requestId,
    ): InventoryItem {
        /*
         * The delivery attempt is the idempotency record for this
         * particular request_id.
         *
         * A successful attempt is always terminal for that request:
         * return the already issued inventory item without calling
         * a provider again.
         */
        $attempt = $this->getOrCreateAttempt(
            $order,
            $requestId,
        );

        if ($attempt->status === DeliveryAttemptStatus::SUCCESS) {
            return $attempt->inventoryItem()->firstOrFail();
        }

        /*
         * Read the current order state from the database.
         *
         * The Order object passed to this service may be stale because
         * a previous delivery attempt can change the status through a
         * separate Eloquent query.
         */
        $currentOrder = Order::query()
            ->whereKey($order->id)
            ->firstOrFail();

        /*
         * If the order was already delivered but this particular
         * delivery attempt was not marked successful yet, synchronize
         * the attempt with the already delivered inventory item.
         *
         * This also protects against a repeated request after delivery.
         */
        if ($currentOrder->status === OrderStatus::DELIVERED) {
            $item = $currentOrder->inventoryItem()->firstOrFail();

            $attempt->update([
                'status' => DeliveryAttemptStatus::SUCCESS,
                'inventory_item_id' => $item->id,
                'code' => $item->code,
                'finished_at' => now(),
                'error' => null,
            ]);

            return $item;
        }

        /*
         * Delivery may start only from PAID or continue from DELIVERING.
         *
         * OUT_OF_STOCK and DELIVERY_FAILED are recoverable states, but
         * the order must first be explicitly restored to PAID before
         * another delivery attempt is started.
         */
        if (
            $currentOrder->status !== OrderStatus::PAID
            && $currentOrder->status !== OrderStatus::DELIVERING
        ) {
            $message = 'Order is not ready for delivery.';

            $attempt->update([
                'status' => DeliveryAttemptStatus::FAILED,
                'finished_at' => now(),
                'error' => $message,
            ]);

            throw new OrderNotReadyException($message);
        }

        /*
         * Short transaction only.
         *
         * We deliberately do NOT call the external provider while this
         * transaction is open. Holding a row lock during a network
         * request would unnecessarily block concurrent requests.
         */
        DB::transaction(function () use ($order): void {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::DELIVERED) {
                return;
            }

            if (
                $lockedOrder->status !== OrderStatus::PAID
                && $lockedOrder->status !== OrderStatus::DELIVERING
            ) {
                throw new OrderNotReadyException(
                    'Order is not ready for delivery.'
                );
            }

            $lockedOrder->update([
                'status' => OrderStatus::DELIVERING,
            ]);
        });

        /*
         * The provider call is intentionally outside the database
         * transaction.
         *
         * Provider A/B have their own request_id idempotency. Therefore
         * a retry with the same request_id is safe even if the previous
         * provider response was lost.
         */
        try {
            $result = $this->orchestrator->issue(
                requestId: $requestId,
                sku: $currentOrder->sku,
                orderId: $currentOrder->public_id,
            );

            /*
             * Provider returned a code.
             *
             * The provider stub already bound the inventory item to
             * the order. We now atomically synchronize our local order
             * and delivery attempt state.
             */
            if ($result->status === 'ok' && $result->code !== null) {
                return DB::transaction(
                    function () use (
                        $order,
                        $attempt,
                        $result,
                    ): InventoryItem {
                        $lockedOrder = Order::query()
                            ->whereKey($order->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $item = InventoryItem::query()
                            ->where('order_id', $lockedOrder->id)
                            ->where('code', $result->code)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $lockedOrder->update([
                            'status' => OrderStatus::DELIVERED,
                        ]);

                        $attempt->update([
                            'status' => DeliveryAttemptStatus::SUCCESS,
                            'inventory_item_id' => $item->id,
                            'code' => $item->code,
                            'finished_at' => now(),
                            'error' => null,
                        ]);

                        return $item;
                    }
                );
            }

            /*
             * Both providers reported that no inventory is available.
             *
             * This is a recoverable business state. The order remains
             * paid in the business sense, but delivery cannot currently
             * be completed.
             */
            if (
                $result->status === 'error'
                && $result->reason === 'out_of_stock'
            ) {
                throw new OutOfStockException(
                    'Product is out of stock.'
                );
            }

            /*
             * Any other definitive provider failure means that both
             * provider paths failed to deliver the order.
             */
            throw new \RuntimeException(
                $result->reason ?? 'Delivery provider failed.'
            );
        } catch (ProviderTimeoutException $e) {
            /*
             * Timeout is ambiguous.
             *
             * The provider may already have issued the code before the
             * response was lost. Therefore:
             *
             * - do not call Provider B;
             * - do not mark the order as failed;
             * - keep the order in DELIVERING;
             * - mark this attempt as TIMEOUT;
             * - retry with the SAME request_id.
             */
            $attempt->update([
                'status' => DeliveryAttemptStatus::TIMEOUT,
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (OutOfStockException $e) {
            /*
             * Inventory exhaustion is recoverable.
             */
            DB::transaction(function () use ($order, $attempt, $e): void {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedOrder->update([
                    'status' => OrderStatus::OUT_OF_STOCK,
                ]);

                $attempt->update([
                    'status' => DeliveryAttemptStatus::FAILED,
                    'finished_at' => now(),
                    'error' => $e->getMessage(),
                ]);
            });

            throw $e;
        } catch (\Throwable $e) {
            /*
             * Any definitive delivery failure is recoverable through a
             * later manual/background retry after restoring the order
             * to PAID.
             */
            DB::transaction(function () use ($order, $attempt, $e): void {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedOrder->update([
                    'status' => OrderStatus::DELIVERY_FAILED,
                ]);

                $attempt->update([
                    'status' => DeliveryAttemptStatus::FAILED,
                    'finished_at' => now(),
                    'error' => $e->getMessage(),
                ]);
            });

            throw $e;
        }
    }

    private function getOrCreateAttempt(
        Order $order,
        string $requestId,
    ): DeliveryAttempt {
        $attempt = DeliveryAttempt::query()
            ->where('request_id', $requestId)
            ->first();

        if ($attempt) {
            if ($attempt->order_id !== $order->id) {
                throw new IdempotencyKeyConflictException(
                    'Idempotency-Key has already been used for another order.'
                );
            }

            return $attempt;
        }

        try {
            return DeliveryAttempt::query()->create([
                'order_id' => $order->id,
                'provider' => 'inventory',
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::PROCESSING,
                'started_at' => now(),
            ]);
        } catch (QueryException $e) {
            /*
             * Another concurrent request may have inserted the same
             * idempotency key between our SELECT and INSERT.
             *
             * PostgreSQL unique constraint is the source of truth.
             */
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            $attempt = DeliveryAttempt::query()
                ->where('request_id', $requestId)
                ->firstOrFail();

            if ($attempt->order_id !== $order->id) {
                throw new IdempotencyKeyConflictException(
                    'Idempotency-Key has already been used for another order.'
                );
            }

            return $attempt;
        }
    }
}
