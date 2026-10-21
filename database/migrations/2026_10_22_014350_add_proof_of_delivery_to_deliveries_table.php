<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            // Random (non-guessable) filename on the 'public' disk — see
            // ProofPhotoController, which is the only place this path is
            // ever read; no view/email/API response ever prints it directly.
            $table->string('proof_photo_path')->nullable()->after('delivered_at');
            $table->string('received_by')->nullable()->after('proof_photo_path');
            $table->text('delivery_note')->nullable()->after('received_by');
            $table->decimal('proof_lat', 10, 7)->nullable()->after('delivery_note');
            $table->decimal('proof_lng', 10, 7)->nullable()->after('proof_lat');
            $table->decimal('proof_accuracy', 8, 2)->nullable()->after('proof_lng');
            $table->timestamp('customer_confirmed_at')->nullable()->after('proof_accuracy');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'proof_photo_path', 'received_by', 'delivery_note',
                'proof_lat', 'proof_lng', 'proof_accuracy', 'customer_confirmed_at',
            ]);
        });
    }
};
