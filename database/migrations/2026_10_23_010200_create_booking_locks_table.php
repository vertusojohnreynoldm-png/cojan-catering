<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per capacity bucket ("catering:2026-11-05",
        // "slot:2026-11-05 10:00", "day:2026-11-05"). Never read for data —
        // it only exists so App\Services\BookingCapacity has a concrete row
        // to SELECT ... FOR UPDATE on, which serialises two customers racing
        // for the last slot in the same bucket.
        Schema::create('booking_locks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_locks');
    }
};
