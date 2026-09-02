<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function handle(array $data): void
    {
        DB::transaction(function () use ($data): void {
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
                    return;
                }

                PaymentEvent::create([
                    'event_id' => $data['event_id'],
                    'order_id' => null,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'payload' => $data,
                    'event_created_at' => Carbon::parse($data['created_at']),
                    'processed_at' => null,
                ]);

                return;
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
                    return;
                }
            } else {
                /*
                 * Validate paid amount/currency before storing the event.
                 */
                $this->validatePaidPayment($order, $data);

                $event = PaymentEvent::create([
                    'event_id' => $data['event_id'],
                    'order_id' => $order->id,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'payload' => $data,
                    'event_created_at' => Carbon::parse($data['created_at']),
                    'processed_at' => null,
                ]);
            }

            /*
             * Recalculate the order state from the newest payment event.
             *
             * This prevents an older webhook that arrives later from
             * overwriting a newer payment state.
             */
            $latestEvent = PaymentEvent::query()
                ->where('order_id', $order->id)
                ->whereNotNull('event_created_at')
                ->orderByDesc('event_created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($latestEvent === null) {
                return;
            }

            if ($latestEvent->status === PaymentStatus::PAID) {
                $this->validatePaidEvent($order, $latestEvent);

                if ($order->status !== OrderStatus::DELIVERED) {
                    $order->update([
                        'status' => OrderStatus::PAID,
                    ]);
                }
            } else {
                if (! in_array($order->status, [
                    OrderStatus::DELIVERED,
                ], true)) {
                    $order->update([
                        'status' => OrderStatus::PAYMENT_FAILED,
                    ]);
                }
            }

            $event->update([
                'processed_at' => now(),
            ]);
        });
    }

    private function validatePaidPayment(Order $order, array $data): void
    {
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
        PaymentEvent $event
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
