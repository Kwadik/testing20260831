<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PromoCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SteamTopUpService
{
    public function create(
        int $amount,
        string $currency,
        ?string $promoCode = null,
        ?string $publicId = null,
    ): Order {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $currency = strtoupper($currency);

        return DB::transaction(function () use (
            $amount,
            $currency,
            $promoCode,
            $publicId,
        ): Order {
            $discountAmount = 0;
            $normalizedPromoCode = null;

            if ($promoCode !== null && $promoCode !== '') {
                $normalizedPromoCode = strtoupper(trim($promoCode));

                $promo = PromoCode::query()
                    ->where('code', $normalizedPromoCode)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($promo === null) {
                    throw ValidationException::withMessages([
                        'promo_code' => 'Promo code is invalid.',
                    ]);
                }

                if ($promo->used_count >= $promo->max_uses) {
                    throw ValidationException::withMessages([
                        'promo_code' => 'Promo code usage limit has been reached.',
                    ]);
                }

                if (
                    $promo->type === 'amount'
                    && $promo->currency !== $currency
                ) {
                    throw ValidationException::withMessages([
                        'promo_code' => 'Promo code is not valid for this currency.',
                    ]);
                }

                $discountAmount = $this->calculateDiscount(
                    amount: $amount,
                    currency: $currency,
                    promo: $promo,
                );

                $promo->increment('used_count');
            }

            $finalAmount = max(0, $amount - $discountAmount);

            $order = Order::query()->create([
                'public_id' => $publicId ?? (string) Str::uuid(),
                'product_id' => null,
                'sku' => 'STEAM-TOPUP',
                'amount' => $finalAmount,
                'currency' => $currency,
                'status' => OrderStatus::CREATED,
                'promo_code' => $normalizedPromoCode,
                'discount_amount' => $discountAmount,
            ]);

            return $order->fresh();
        });
    }

    private function calculateDiscount(
        int $amount,
        string $currency,
        PromoCode $promo,
    ): int {
        return match ($promo->type) {
            'percent' => intdiv(
                $amount * $promo->value,
                100,
            ),

            'amount' => min(
                $amount,
                $promo->value,
            ),

            default => throw ValidationException::withMessages([
                'promo_code' => 'Promo code type is invalid.',
            ]),
        };
    }
}
