<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('is_read');
            $table->index(['receiver_id', 'read_at'], 'messages_receiver_id_read_at_index');
        });

        // Backfill only already-read rows so this doesn't flip every historical
        // message into a fresh "unread" item for the admin inbox — rows that
        // were already unread (is_read = false) correctly stay read_at = null.
        DB::table('messages')->where('is_read', true)->update(['read_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_receiver_id_read_at_index');
            $table->dropColumn('read_at');
        });
    }
};
