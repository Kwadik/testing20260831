<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentSimulationController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function pay(string $publicId): JsonResponse
    {
        $order = Order::query()
            ->where('public_id', $publicId)
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        $this->paymentService->handle([
            'event_id' => 'evt_sim_' . bin2hex(random_bytes(16)),
            'order_id' => $order->public_id,
            'status' => 'paid',
            'amount' => $order->amount,
            'currency' => $order->currency,
            'created_at' => now()->toISOString(),
        ]);

        return response()->json([
            'status' => 'accepted',
        ]);
    }
}
