<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->timestampTz('event_created_at')
                ->nullable()
                ->after('payload');

            $table->index([
                'order_id',
                'event_created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table) {
            $table->dropIndex('payment_events_order_id_event_created_at_index');
            $table->dropColumn('event_created_at');
        });
    }
};
