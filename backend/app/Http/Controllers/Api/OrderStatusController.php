<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderStatusController extends Controller
{
    public function show(string $publicId): JsonResponse
    {
        $order = Order::query()
            ->where('public_id', $publicId)
            ->with('deliveryAttempts')
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        $deliveryAttempt = $order->deliveryAttempts
            ->sortByDesc('id')
            ->first();

        return response()->json([
            'data' => [
                'id' => $order->public_id,
                'status' => $order->status->value,
                'sku' => $order->sku,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'delivery' => $deliveryAttempt === null
                    ? null
                    : [
                        'status' => $deliveryAttempt->status->value,
                    ],
            ],
        ]);
    }
}
