<?php

namespace App\Services\Delivery;

use App\Enums\InventoryStatus;
use App\Exceptions\ProviderTimeoutException;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\ProviderIssuance;
use Illuminate\Support\Facades\DB;

class ProviderA implements DeliveryProvider
{
    public function issue(
        string $requestId,
        string $sku,
        string $orderId,
    ): ProviderIssueResult {
        $order = Order::query()
            ->where('public_id', $orderId)
            ->firstOrFail();

        DB::table('provider_issuances')->insertOrIgnore([
            'provider' => 'provider_a',
            'request_id' => $requestId,
            'order_id' => $order->id,
            'sku' => $sku,
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = DB::transaction(function () use (
            $requestId,
            $sku,
            $orderId,
        ): ProviderIssueResult {
            $issuance = ProviderIssuance::query()
                ->where('provider', 'provider_a')
                ->where('request_id', $requestId)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $issuance->status === 'success'
                && $issuance->code !== null
            ) {
                return ProviderIssueResult::success(
                    $issuance->code,
                );
            }

            $order = Order::query()
                ->where('public_id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->sku !== $sku) {
                throw new \RuntimeException(
                    'Provider request SKU does not match order.'
                );
            }

            $mode = config('delivery.provider_a.mode', 'normal');

            if ($mode === 'error') {
                $issuance->update([
                    'status' => 'error',
                    'reason' => 'provider_error',
                ]);

                return ProviderIssueResult::error(
                    'provider_error',
                );
            }

            $item = InventoryItem::query()
                ->where('product_id', $order->product_id)
                ->where('status', InventoryStatus::AVAILABLE)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                $issuance->update([
                    'status' => 'error',
                    'reason' => 'out_of_stock',
                ]);

                return ProviderIssueResult::error(
                    'out_of_stock',
                );
            }

            $item->update([
                'status' => InventoryStatus::DELIVERED,
                'order_id' => $order->id,
            ]);

            $issuance->update([
                'status' => 'success',
                'inventory_item_id' => $item->id,
                'code' => $item->code,
                'reason' => null,
            ]);

            return ProviderIssueResult::success(
                $item->code,
            );
        });

        if (
            config('delivery.provider_a.mode') === 'timeout_after_issue'
            && $result->status === 'ok'
        ) {
            throw new ProviderTimeoutException(
                'Provider A response timed out after issuance.'
            );
        }

        return $result;
    }
}
