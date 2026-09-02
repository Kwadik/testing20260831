<?php

namespace Tests\Feature\Services;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProviderAConcurrencyTest extends TestCase
{
    public function test_same_request_id_is_idempotent_under_concurrency(): void
    {
        Artisan::call('migrate:fresh', [
            '--force' => true,
        ]);

        try {
            $product = Product::factory()->create([
                'sku' => 'STEAM-TOPUP-500',
                'price' => 500,
                'currency' => 'RUB',
                'is_active' => true,
            ]);

            $order = Order::query()->create([
                'public_id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'sku' => $product->sku,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => 'paid',
            ]);

            InventoryItem::factory()->create([
                'product_id' => $product->id,
                'code' => 'LFXC-TNCS-BPCD',
                'status' => 'available',
                'order_id' => null,
            ]);

            $requestId = 'req_concurrent_001';

            $script = base_path('tests/Support/provider_a_parallel.php');

            file_put_contents($script, <<<'PHP'
<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$provider = app(\App\Services\Delivery\ProviderA::class);

$result = $provider->issue(
    requestId: $argv[1],
    sku: $argv[2],
    orderId: $argv[3],
);

echo json_encode([
    'status' => $result->status,
    'code' => $result->code,
    'reason' => $result->reason,
]);
PHP);

            $command = sprintf(
                'php %s %s %s %s',
                escapeshellarg($script),
                escapeshellarg($requestId),
                escapeshellarg($product->sku),
                escapeshellarg($order->public_id),
            );

            $processes = [];
            $outputs = [];

            for ($i = 0; $i < 2; $i++) {
                $processes[$i] = proc_open(
                    $command,
                    [
                        1 => ['pipe', 'w'],
                        2 => ['pipe', 'w'],
                    ],
                    $pipes,
                );

                $outputs[$i] = [
                    'stdout' => $pipes[1],
                    'stderr' => $pipes[2],
                ];
            }

            foreach ($processes as $index => $process) {
                $stdout = stream_get_contents(
                    $outputs[$index]['stdout']
                );

                $stderr = stream_get_contents(
                    $outputs[$index]['stderr']
                );

                fclose($outputs[$index]['stdout']);
                fclose($outputs[$index]['stderr']);

                $exitCode = proc_close($process);

                $this->assertSame(
                    0,
                    $exitCode,
                    "Provider process {$index} failed:\n{$stderr}"
                );

                $result = json_decode($stdout, true);

                $this->assertIsArray(
                    $result,
                    "Provider process {$index} returned invalid JSON.\n"
                    ."STDOUT:\n{$stdout}\n"
                    ."STDERR:\n{$stderr}\n"
                    ."JSON error:\n".json_last_error_msg()
                );

                $this->assertSame('ok', $result['status']);
                $this->assertSame(
                    'LFXC-TNCS-BPCD',
                    $result['code']
                );
            }

            $this->assertDatabaseCount(
                'provider_issuances',
                1
            );

            $this->assertDatabaseHas(
                'provider_issuances',
                [
                    'provider' => 'provider_a',
                    'request_id' => $requestId,
                    'order_id' => $order->id,
                    'status' => 'success',
                    'code' => 'LFXC-TNCS-BPCD',
                ]
            );

            $this->assertDatabaseCount(
                'inventory_items',
                1
            );

            $this->assertDatabaseHas(
                'inventory_items',
                [
                    'status' => 'delivered',
                    'order_id' => $order->id,
                ]
            );
        } finally {
            Artisan::call('migrate');
        }
    }
}
