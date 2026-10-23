<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The customer's map pin — menu-order deliveries only (catering
            // bookings never set it). NULL on orders placed before pins
            // existed; App\Services\DeliveryLocation falls back to the
            // barangay centre for those, marked "approximate".
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            // Saved default pin ("Save as my default location" at checkout),
            // pre-filled on the customer's next menu checkout.
            $table->decimal('default_latitude', 10, 7)->nullable();
            $table->decimal('default_longitude', 10, 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['default_latitude', 'default_longitude']);
        });
    }
};
