<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set = this customer can't place orders or bookings (they can
            // still log in, see their orders and chat). The reason is
            // admin-only; customers see a neutral message.
            $table->timestamp('blocked_at')->nullable();
            $table->string('blocked_reason')->nullable();
            // Snapshot of which admin did it — no FK, same as activity_logs.
            $table->unsignedBigInteger('blocked_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason', 'blocked_by']);
        });
    }
};
