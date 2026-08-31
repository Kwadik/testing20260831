<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function create(string $sku): Order
    {
        $product = Product::query()
            ->where('sku', $sku)
            ->where('is_active', true)
            ->first();

        if ($product === null) {
            throw new NotFoundHttpException('Product not found.');
        }

        return Order::query()->create([
            'public_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::CREATED,
        ]);
    }
}
