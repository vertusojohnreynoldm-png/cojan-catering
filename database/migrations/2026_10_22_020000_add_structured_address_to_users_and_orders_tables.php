<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Additive only — every new column is nullable, no existing column is
    // touched, and no data is backfilled. Old rows simply have these null
    // and keep working off the existing flat delivery_address/address
    // strings, which continue to be populated going forward too (see
    // App\Services\ServiceArea / the controllers that write these columns).
    // Safe to run with `migrate --force` on Railway.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('province')->nullable()->after('address');
            $table->string('municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('municipality');
            $table->string('street')->nullable()->after('barangay');
            $table->string('landmark')->nullable()->after('street');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('province')->nullable()->after('delivery_address');
            $table->string('municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('municipality');
            $table->string('street')->nullable()->after('barangay');
            $table->string('landmark')->nullable()->after('street');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['province', 'municipality', 'barangay', 'street', 'landmark']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['province', 'municipality', 'barangay', 'street', 'landmark']);
        });
    }
};
