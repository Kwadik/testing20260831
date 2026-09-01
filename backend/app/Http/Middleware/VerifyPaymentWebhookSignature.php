<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('X-Signature');
        $secret = config('services.payment.webhook_secret');

        if (! $signature || ! $secret) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            $secret,
        );

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        return $next($request);
    }
}
