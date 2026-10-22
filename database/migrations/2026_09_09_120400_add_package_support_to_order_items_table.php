<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['menu_item_id']);
        });

        // doctrine/dbal (dev dependency) makes ->change() portable across
        // drivers — this previously ran as raw MySQL-only SQL, guarded to a
        // no-op on SQLite under the mistaken assumption that SQLite already
        // created the column nullable (it doesn't: the base migration never
        // called ->nullable()). That left menu_item_id still NOT NULL on
        // SQLite, which the test suite never caught until a test actually
        // exercised a package-only (no menu_item_id) order item. Already
        // applied as the old raw ALTER on the real MySQL dev/Railway DB —
        // this edit only changes what a *fresh* migration run does.
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->nullable()->change();
        });

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

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('menu_item_id')
                ->references('id')->on('menu_items')
                ->onDelete('restrict');
        });
    }
};
