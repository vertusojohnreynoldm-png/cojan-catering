<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Direction of travel (0–360°, 0 = north) and speed (m/s) from the
            // rider's last location update — points the rider marker the way
            // they're heading. NULL when the phone doesn't report them.
            $table->decimal('current_heading', 5, 1)->nullable();
            $table->decimal('current_speed', 6, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['current_heading', 'current_speed']);
        });
    }
};
