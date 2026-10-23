<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Catering bookings only. A booking is "Pending Payment" while
            // status = pending, payment_due_at is set and downpayment_paid_at
            // is still empty (see Order::isAwaitingDownpayment()) — the
            // status enum itself is untouched.
            $table->decimal('downpayment_amount', 10, 2)->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('downpayment_paid_at')->nullable();
            // GCash receipt the customer uploads — a UUID filename on the
            // public disk, only ever served through ReceiptController.
            $table->string('receipt_path')->nullable();
            $table->timestamp('receipt_uploaded_at')->nullable();
            $table->timestamp('receipt_rejected_at')->nullable();
            $table->string('receipt_rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'downpayment_amount', 'payment_due_at', 'downpayment_paid_at',
                'receipt_path', 'receipt_uploaded_at', 'receipt_rejected_at', 'receipt_rejection_reason',
            ]);
        });
    }
};
