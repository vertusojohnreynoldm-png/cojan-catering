<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The one-time submission token the checkout form was rendered
            // with (App\Services\SubmissionToken). Unique, so a double click
            // or a slow-network retry that races past the session check still
            // can't insert a second order — the database refuses it. Nullable
            // (and NULLs never collide) so every existing order is untouched.
            $table->string('idempotency_key', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
