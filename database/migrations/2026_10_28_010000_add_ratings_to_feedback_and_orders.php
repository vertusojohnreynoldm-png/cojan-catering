<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Ratings for food and service. Reuses the existing `feedback` table (one row
// per order; its `rating` becomes the overall-experience score, so older rows
// stay valid) and adds `feedback_items` for per-item stars. Additive only.
return new class extends Migration
{
    public function up(): void
    {
        // When an order became Delivered (a booking: Completed) — the rating
        // window counts from here. Kept up to date by OrderObserver.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
        });

        // Existing delivered orders: the rider's delivered time, else the
        // order's last update (the closest record of when it was delivered).
        DB::table('orders')->where('status', 'delivered')->whereNull('completed_at')
            ->orderBy('id')->select('id', 'updated_at')
            ->chunkById(200, function ($orders) {
                $delivered = DB::table('deliveries')->whereIn('order_id', $orders->pluck('id'))
                    ->whereNotNull('delivered_at')->pluck('delivered_at', 'order_id');
                foreach ($orders as $order) {
                    DB::table('orders')->where('id', $order->id)
                        ->update(['completed_at' => $delivered[$order->id] ?? $order->updated_at]);
                }
            });

        Schema::table('feedback', function (Blueprint $table) {
            // Menu order: delivery/rider · Booking: setup & staff
            $table->unsignedTinyInteger('rating_service')->nullable()->after('rating');
            // Menu order: packaging & food condition · Booking: food quality
            $table->unsignedTinyInteger('rating_food')->nullable()->after('rating_service');
            // Who delivered it, copied at submit time (no FK, like the activity log).
            $table->unsignedBigInteger('rider_id')->nullable()->after('rating_food');
            $table->string('photo_path')->nullable()->after('comment');
            $table->timestamp('edited_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->string('hidden_reason')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->unsignedBigInteger('hidden_by')->nullable();
            $table->text('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->unsignedBigInteger('replied_by')->nullable();
            $table->index('rider_id');
            $table->index('is_hidden');
        });

        // One rating per order. The app enforces it either way; the index is a
        // backstop, added only when no duplicates already exist so this can
        // never fail on production (duplicates are left alone, not deleted).
        $hasDuplicates = DB::table('feedback')->select('order_id')
            ->groupBy('order_id')->havingRaw('COUNT(*) > 1')->exists();
        if (!$hasDuplicates) {
            Schema::table('feedback', function (Blueprint $table) {
                $table->unique('order_id', 'feedback_order_id_unique');
            });
        }

        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedback')->cascadeOnDelete();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('menu_item_id')->nullable()->index();
            $table->unsignedBigInteger('package_id')->nullable()->index();
            $table->unsignedTinyInteger('rating');
            $table->string('comment', 300)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_items');

        if (Schema::hasIndex('feedback', 'feedback_order_id_unique')) {
            // MySQL let the unique index stand in for the order_id foreign
            // key's own index, so the key needs a plain index back first.
            Schema::table('feedback', fn (Blueprint $table) => $table->index('order_id', 'feedback_order_id_index'));
            Schema::table('feedback', fn (Blueprint $table) => $table->dropUnique('feedback_order_id_unique'));
        }
        Schema::table('feedback', function (Blueprint $table) {
            $table->dropIndex(['rider_id']);
            $table->dropIndex(['is_hidden']);
            $table->dropColumn([
                'rating_service', 'rating_food', 'rider_id', 'photo_path', 'edited_at',
                'is_hidden', 'hidden_reason', 'hidden_at', 'hidden_by',
                'admin_reply', 'replied_at', 'replied_by',
            ]);
        });

        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('completed_at'));
    }
};
