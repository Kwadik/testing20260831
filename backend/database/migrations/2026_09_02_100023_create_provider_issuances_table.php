<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_issuances', function (Blueprint $table) {
            $table->id();

            $table->string('provider', 50);

            $table->string('request_id', 200);

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('sku', 150);

            $table->string('status', 30);

            $table->foreignId('inventory_item_id')
                ->nullable()
                ->constrained('inventory_items')
                ->nullOnDelete();

            $table->string('code', 500)->nullable();

            $table->string('reason', 500)->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'request_id',
            ]);

            $table->index([
                'order_id',
                'provider',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_issuances');
    }
};
