<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // "QR verified at hand-off": the assigned rider scanned the
            // customer's order QR while the order was picked up / in transit.
            // Optional — a delivery can still be completed without it.
            $table->timestamp('qr_scanned_at')->nullable()->after('customer_confirmed_at');
            $table->foreignId('qr_scanned_by')->nullable()->after('qr_scanned_at')->constrained('users')->nullOnDelete();
            $table->decimal('qr_scan_lat', 10, 7)->nullable()->after('qr_scanned_by');
            $table->decimal('qr_scan_lng', 10, 7)->nullable()->after('qr_scan_lat');
            $table->decimal('qr_scan_accuracy', 8, 2)->nullable()->after('qr_scan_lng');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_scanned_by');
            $table->dropColumn(['qr_scanned_at', 'qr_scan_lat', 'qr_scan_lng', 'qr_scan_accuracy']);
        });
    }
};
