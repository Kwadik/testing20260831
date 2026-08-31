<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Support\Facades\DB;

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

            $order = Order::where('public_id', $data['order_id'])
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return;
            }

            if (in_array($order->status, [
                'delivered',
                'payment_failed',
            ], true)) {
                return;
            }
//            if ($order->isFinal()) {
//                return;
//            }

            if ($data['status'] === 'paid') {
                $order->update([
                    'status' => 'paid',
                ]);

                return;
            }

            $order->update([
                'status' => 'payment_failed',
            ]);
        });
    }
}
