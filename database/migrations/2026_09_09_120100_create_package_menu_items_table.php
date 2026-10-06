<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purely descriptive/display data — which dishes make up a package and
        // in what quantity. The package's own fixed price is what's charged,
        // never derived from these rows, so cascading on delete here is safe:
        // it's not order history (that lives on order_items.package_id).
        Schema::create('package_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('catering_packages')->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained('menu_items')->onDelete('cascade');
            $table->integer('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_menu_items');
    }
};
