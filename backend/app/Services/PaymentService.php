<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function handle(array $data): void
    {
        $order = Order::where('public_id', $data['order_id'])->firstOrFail();

        DB::transaction(function () use ($data, $order): void {
            $event = PaymentEvent::firstOrCreate(
                ['event_id' => $data['event_id']],
                [
                    'order_id' => $order->id,
                    'status' => $data['status'],
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'payload' => $data,
                    'processed_at' => now(),
                ]
            );

            if (! $event->wasRecentlyCreated) {
                return;
            }

            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($order->status, [
                OrderStatus::DELIVERED,
                OrderStatus::PAYMENT_FAILED,
            ], true)) {
                return;
            }

            if (
                $data['status'] === PaymentStatus::PAID->value
                && (
                    $order->amount !== $data['amount']
                    || $order->currency !== $data['currency']
                )
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount or currency does not match order.',
                    'currency' => 'Payment amount or currency does not match order.',
                ]);
            }

            if ($data['status'] === PaymentStatus::PAID->value) {
                $order->update([
                    'status' => OrderStatus::PAID,
                ]);

                return;
            }

            $order->update([
                'status' => OrderStatus::PAYMENT_FAILED,
            ]);
        });
    }
}
