<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('gcash_reference')->nullable()->after('payment_method');
        });

        // Laravel's schema builder can't safely widen a MySQL ENUM's allowed
        // values without Doctrine DBAL (which handles enums poorly), so this
        // is done with a raw ALTER TABLE instead.
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cash_on_delivery', 'gcash') NOT NULL DEFAULT 'cash_on_delivery'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY payment_method ENUM('cash_on_delivery') NOT NULL DEFAULT 'cash_on_delivery'");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('gcash_reference');
        });
    }
};
