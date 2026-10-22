<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 'no_show' | 'fake' — only ever set together with
            // status = 'cancelled', so the status enum itself is untouched.
            $table->string('flag', 20)->nullable();
            // Why it was cancelled/flagged (admin note; Stage 4's auto-expiry
            // reuses it). Shown to admins only.
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('flagged_at')->nullable();
            $table->unsignedBigInteger('flagged_by')->nullable();
            // utensil_id => quantity this order took out of stock when it
            // was placed, so a cancellation can give back exactly that much
            // even if the package's contents change later. NULL on orders
            // placed before this column existed.
            $table->json('reserved_utensils')->nullable();
            // Set the moment cancelled stock is returned — the guard that
            // makes a second restore a no-op.
            $table->timestamp('stock_restored_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['flag', 'cancellation_reason', 'flagged_at', 'flagged_by', 'reserved_utensils', 'stock_restored_at']);
        });
    }
};
