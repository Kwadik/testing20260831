<?php

use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderDeliveryController;
use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);

Route::post('/payment/webhook', [PaymentWebhookController::class, 'handle']);

Route::post(
    '/orders/{publicId}/deliver',
    [OrderDeliveryController::class, 'deliver']
);
