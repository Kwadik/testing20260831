<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\DeliverOrderJob;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function handle(array $data): bool
    {
        $shouldDeliver = DB::transaction(function () use ($data): bool {
            $order = Order::query()
                ->where('public_id', $data['order_id'])
                ->lockForUpdate()
                ->first();

            /*
             * The webhook may arrive before the order is created.
             */
            if ($order === null) {
                $existingEvent = PaymentEvent::query()
                    ->where('event_id', $data['event_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existingEvent !== null) {
                    return false;
                }

                try {
                    PaymentEvent::create([
                        'event_id' => $data['event_id'],
                        'order_id' => null,
                        'status' => $data['status'],
                        'amount' => $data['amount'],
                        'currency' => $data['currency'],
                        'payload' => $data,
                        'event_created_at' => Carbon::parse(
                            $data['created_at'],
                        ),
                        'processed_at' => null,
                    ]);
                } catch (QueryException $e) {
                    if ($e->getCode() !== '23505') {
                        throw $e;
                    }

                    /*
                     * Another concurrent webhook already stored this event.
                     * The event_id is the idempotency key, so this request is done.
                     */
                    return false;
                }

                return false;
            }

            /*
             * Idempotency by event_id.
             */
            $event = PaymentEvent::query()
                ->where('event_id', $data['event_id'])
                ->lockForUpdate()
                ->first();

            if ($event !== null) {
                if (
                    $event->order_id !== null
                    && (int) $event->order_id !== (int) $order->id
                ) {
                    throw ValidationException::withMessages([
                        'event_id' => 'This payment event has already been used for another order.',
                    ]);
                }

                if ($event->order_id === null) {
                    $event->update([
                        'order_id' => $order->id,
                    ]);
                } else {
                    return false;
                }
            } else {
                $this->validatePaidPayment($order, $data);

                $event = PaymentEvent::create([
                    'event_id' => $data['event_id'],
                    'order_id' => $order->id,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'payload' => $data,
                    'event_created_at' => Carbon::parse(
                        $data['created_at'],
                    ),
                    'processed_at' => null,
                ]);
            }

            /*
             * Recalculate state from the newest payment event.
             *
             * Because the order row is locked, concurrent webhooks for
             * the same order are serialized here.
             */
            $latestEvent = PaymentEvent::query()
                ->where('order_id', $order->id)
                ->whereNotNull('event_created_at')
                ->orderByDesc('event_created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latestEvent === null) {
                return false;
            }

            $previousStatus = $order->status;

            if ($latestEvent->status === PaymentStatus::PAID) {
                $this->validatePaidEvent(
                    $order,
                    $latestEvent,
                );

                /*
                 * Never move an order backwards from a delivery state.
                 *
                 * PAID may be restored from recoverable payment/delivery
                 * states, but DELIVERING and DELIVERED must remain stable.
                 */
                if (in_array($order->status, [
                    OrderStatus::CREATED,
                    OrderStatus::PAYMENT_FAILED,
                    OrderStatus::OUT_OF_STOCK,
                    OrderStatus::DELIVERY_FAILED,
                ], true)) {
                    $order->update([
                        'status' => OrderStatus::PAID,
                    ]);
                }
            } else {
                /*
                 * A failed event must not cancel an order that has already
                 * entered delivery or has been successfully delivered.
                 */
                if (in_array($order->status, [
                    OrderStatus::CREATED,
                    OrderStatus::PAID,
                    OrderStatus::PAYMENT_FAILED,
                ], true)) {
                    $order->update([
                        'status' => OrderStatus::PAYMENT_FAILED,
                    ]);
                }
            }

            $event->update([
                'processed_at' => now(),
            ]);

            /*
             * Only a real transition into PAID starts delivery.
             *
             * This prevents 50 different paid events from scheduling
             * 50 independent delivery operations.
             */
            return (
                $previousStatus !== OrderStatus::PAID
                && $previousStatus !== OrderStatus::DELIVERING
                && $previousStatus !== OrderStatus::DELIVERED
                && $order->status === OrderStatus::PAID
            );
        });

        if ($shouldDeliver) {
            $orderId = Order::query()
                ->where('public_id', $data['order_id'])
                ->value('id');

            if ($orderId !== null) {
                DeliverOrderJob::dispatch($orderId)
                    ->afterCommit();
            }
        }

        return $shouldDeliver;
    }

    private function validatePaidPayment(
        Order $order,
        array $data,
    ): void {
        if (
            $data['status'] !== PaymentStatus::PAID->value
            || (
                $order->amount === $data['amount']
                && $order->currency === $data['currency']
            )
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'amount' => 'Payment amount or currency does not match order.',
            'currency' => 'Payment amount or currency does not match order.',
        ]);
    }

    private function validatePaidEvent(
        Order $order,
        PaymentEvent $event,
    ): void {
        if (
            $event->amount !== $order->amount
            || $event->currency !== $order->currency
        ) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount or currency does not match order.',
                'currency' => 'Payment amount or currency does not match order.',
            ]);
        }
    }
}
