<?php

namespace App\Models;

use App\Observers\OrderObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_method',
        'gcash_reference',
        'payment_status',
        'subtotal',
        'delivery_fee',
        'total_amount',
        'delivery_address',
        'province',
        'municipality',
        'barangay',
        'street',
        'landmark',
        'delivery_phone',
        'notes',
        'qr_code',
        'contact_name',
        'event_date',
        'event_type',
        'delivery_type',
        'requested_delivery_at',
        'delivery_window_label',
        'idempotency_key',
        'flag',
        'cancellation_reason',
        'flagged_at',
        'flagged_by',
        'reserved_utensils',
        'stock_restored_at',
        'downpayment_amount',
        'payment_due_at',
        'downpayment_paid_at',
        'receipt_path',
        'receipt_uploaded_at',
        'receipt_rejected_at',
        'receipt_rejection_reason',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'requested_delivery_at' => 'datetime',
        'flagged_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'reserved_utensils' => 'array',
        'payment_due_at' => 'datetime',
        'downpayment_paid_at' => 'datetime',
        'receipt_uploaded_at' => 'datetime',
        'receipt_rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * Catering Package bookings (the Avail flow) are the only orders that
     * ever set event_date — a regular menu order, even one containing a
     * package via the older cart flow, never has it. That makes it the
     * reliable signal here, unlike order_items.package_id, which a
     * cart-based order could also have.
     */
    public function scopeBookings($query)
    {
        return $query->whereNotNull('event_date');
    }

    public function scopeRegularOrders($query)
    {
        return $query->whereNull('event_date');
    }

    public function isBooking(): bool
    {
        return $this->event_date !== null;
    }

    /**
     * "ASAP" / "Today, 10:00 AM - 10:30 AM" — the one place every view
     * (customer, admin, rider, email, activity log) gets this from, so the
     * wording never drifts between them. Bookings don't use this at all —
     * they display $order->event_date directly instead.
     */
    public function deliveryTimeLabel(): string
    {
        if ($this->delivery_type !== 'scheduled' || !$this->requested_delivery_at) {
            return 'ASAP';
        }

        $date = $this->requested_delivery_at->isToday()
            ? 'Today'
            : ($this->requested_delivery_at->isTomorrow() ? 'Tomorrow' : $this->requested_delivery_at->format('M d'));

        return "{$date}, {$this->delivery_window_label}";
    }

    /**
     * True when a scheduled (non-ASAP) delivery is due within the next hour
     * and the order hasn't gone out yet — drives the admin list's "due soon"
     * highlight.
     */
    public function isDeliveryDueSoon(): bool
    {
        return $this->delivery_type === 'scheduled'
            && $this->requested_delivery_at
            && !in_array($this->status, ['out_for_delivery', 'delivered', 'cancelled'], true)
            && $this->requested_delivery_at->isFuture()
            && now()->diffInMinutes($this->requested_delivery_at, false) <= 60;
    }

    public const FLAGS = ['no_show' => 'No-show', 'fake' => 'Fake'];

    // ---- Catering downpayment ---------------------------------------------

    /**
     * "Pending Payment": a catering booking still waiting for its GCash
     * downpayment to be confirmed. Derived from data — the status enum
     * stays 'pending'.
     */
    public function isAwaitingDownpayment(): bool
    {
        return $this->isBooking()
            && $this->status === 'pending'
            && $this->payment_due_at !== null
            && $this->downpayment_paid_at === null;
    }

    /** A receipt is uploaded and an admin hasn't confirmed or rejected it yet. */
    public function hasReceiptUnderReview(): bool
    {
        return $this->isAwaitingDownpayment()
            && $this->receipt_path !== null
            && $this->receipt_rejected_at === null;
    }

    /** The customer can (re-)upload a receipt only while still Pending Payment. */
    public function canUploadReceipt(): bool
    {
        return $this->isAwaitingDownpayment() && !$this->payment_due_at->isPast();
    }

    public function requiresDownpayment(): bool
    {
        return $this->downpayment_amount !== null;
    }

    /** What's left to pay on the event day (total minus the downpayment). */
    public function balanceAmount(): float
    {
        return round((float) $this->total_amount - (float) ($this->downpayment_amount ?? 0), 2);
    }

    /**
     * Admin-only marker for a cancelled order an admin flagged as a no-show
     * or a fake booking. Customers only ever see "Cancelled".
     */
    public function flagLabel(): ?string
    {
        return self::FLAGS[$this->flag] ?? null;
    }

    /** Pending / confirmed / preparing / out for delivery — not finished or cancelled. */
    public function isActive(): bool
    {
        return !in_array($this->status, ['delivered', 'cancelled'], true);
    }

    /**
     * The badge-cj modifier class for this order's status
     * (or an arbitrary status string, for use without an Order instance).
     */
    public function statusBadgeClass(?string $status = null): string
    {
        if ($status === null && $this->isAwaitingDownpayment()) {
            return $this->hasReceiptUnderReview() ? 'badge-review' : 'badge-payment';
        }

        return match ($status ?? $this->status) {
            'delivered'         => 'badge-delivered',
            'cancelled'         => 'badge-cancelled',
            'out_for_delivery'  => 'badge-delivery',
            'preparing'         => 'badge-preparing',
            'confirmed'         => 'badge-confirmed',
            default             => 'badge-pending',
        };
    }

    /**
     * Human-readable label for this order's status
     * (or an arbitrary status string, for use without an Order instance).
     * Bookings relabel 'delivered' as 'Completed' — a catering booking isn't
     * "delivered" the way a food order is.
     */
    public function statusLabel(?string $status = null): string
    {
        // The derived payment sub-states only apply to this order's own,
        // current status — never to an explicit $status (e.g. the "from"
        // side of an activity-log entry).
        if ($status === null && $this->isAwaitingDownpayment()) {
            return $this->hasReceiptUnderReview() ? 'Payment Under Review' : 'Pending Payment';
        }

        $status = $status ?? $this->status;

        if ($this->isBooking() && $status === 'delivered') {
            return 'Completed';
        }

        return ucfirst(str_replace('_', ' ', $status));
    }
}