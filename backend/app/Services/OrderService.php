<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function create(string $sku, ?string $publicId = null): Order
    {
        $product = Product::query()
            ->where('sku', $sku)
            ->where('is_active', true)
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        return DB::transaction(function () use ($product, $publicId): Order {
            $order = Order::query()->create([
                'public_id' => $publicId ?? (string) Str::uuid(),
                'product_id' => $product->id,
                'sku' => $product->sku,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => OrderStatus::CREATED,
            ]);

            $shouldDeliver = false;

            $pendingEvent = PaymentEvent::query()
                ->whereNull('order_id')
                ->whereNull('processed_at')
                ->whereRaw(
                    "payload->>'order_id' = ?",
                    [$order->public_id],
                )
                ->lockForUpdate()
                ->first();

            if ($pendingEvent !== null) {
                if (
                    $pendingEvent->status === \App\Enums\PaymentStatus::PAID
                    && (
                        $pendingEvent->amount !== $order->amount
                        || $pendingEvent->currency !== $order->currency
                    )
                ) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'amount' => 'Payment amount or currency does not match order.',
                        'currency' => 'Payment amount or currency does not match order.',
                    ]);
                }

                if ($pendingEvent->status === \App\Enums\PaymentStatus::PAID) {
                    $order->update([
                        'status' => OrderStatus::PAID,
                    ]);
                } else {
                    $order->update([
                        'status' => OrderStatus::PAYMENT_FAILED,
                    ]);
                }

                $pendingEvent->update([
                    'order_id' => $order->id,
                    'processed_at' => now(),
                ]);
            }

            if ($shouldDeliver) {
                DeliverOrderJob::dispatch($order->id)
                    ->afterCommit();
            }

            return $order->fresh();
        });
    }
}
