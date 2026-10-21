<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Defaulted (not nullable) so every existing row is automatically
            // backfilled as 'asap' by the ALTER TABLE itself — no separate
            // backfill step needed, and old code paths that never set this
            // column keep working unchanged.
            $table->enum('delivery_type', ['asap', 'scheduled'])->default('asap')->after('delivery_fee');
            $table->timestamp('requested_delivery_at')->nullable()->after('delivery_type');
            $table->string('delivery_window_label')->nullable()->after('requested_delivery_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'requested_delivery_at', 'delivery_window_label']);
        });
    }
};
