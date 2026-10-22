<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_dates', function (Blueprint $table) {
            $table->id();
            // Stored as a plain 'Y-m-d' string-compatible DATE so lookups are
            // a simple equality on both MySQL and the SQLite test database.
            $table->date('date')->unique();
            $table->string('reason')->nullable();
            // Snapshot of who blocked it, not a FK — same reasoning as
            // activity_logs: deleting an admin account mustn't touch this.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_dates');
    }
};
