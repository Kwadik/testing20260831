<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Потенциально циклическая FK-связь. Пока будем получать код через inventory_items.order_id
//        $table->foreignId('delivery_code_id')
//            ->nullable()
//            ->unique()
//            ->constrained('inventory_items')
//            ->nullOnDelete();

            $table->uuid('public_id')->unique();

            $table->foreignId('product_id')
                ->constrained()
                ->restrictOnDelete();

            // Snapshot товара на момент покупки.
            $table->string('sku', 100);

            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);

            $table->string('status', 30)->index();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
