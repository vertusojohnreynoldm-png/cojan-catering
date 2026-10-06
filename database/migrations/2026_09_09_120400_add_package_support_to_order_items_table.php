<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen menu_item_id to nullable via raw SQL (not ->change(), which
        // needs doctrine/dbal — not a direct dependency of this project).
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
        });

        DB::statement('ALTER TABLE order_items MODIFY menu_item_id BIGINT UNSIGNED NULL');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('menu_item_id')
                ->references('id')->on('menu_items')
                ->onDelete('restrict');

            // restrict(), not cascade — mirrors the menu_item_id fix above:
            // deleting a package that has order history should be blocked,
            // not silently destroy past order line items. Admins "delete" a
            // package via soft-delete + is_available=false instead.
            $table->foreignId('package_id')->nullable()->after('menu_item_id')
                ->constrained('catering_packages')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropColumn('package_id');
            $table->dropForeign(['menu_item_id']);
        });

        DB::statement('ALTER TABLE order_items MODIFY menu_item_id BIGINT UNSIGNED NOT NULL');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('menu_item_id')
                ->references('id')->on('menu_items')
                ->onDelete('restrict');
        });
    }
};
