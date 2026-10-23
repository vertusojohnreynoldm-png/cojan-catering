<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 2026_09_09_000100 widened payment_method to include 'gcash' with a
     * MySQL-only ALTER, so on SQLite (the test database) the column's CHECK
     * constraint still only allowed 'cash_on_delivery' and no test could
     * create a GCash order. Catering downpayments are always GCash, so the
     * tests need it now. SQLite only — MySQL (production) already has it,
     * so this is a no-op there.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['cash_on_delivery', 'gcash'])->default('cash_on_delivery')->change();
        });
    }

    public function down(): void
    {
        // Nothing to undo on MySQL; narrowing it again on SQLite would only
        // break rows that already use 'gcash'.
    }
};
