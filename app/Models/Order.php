<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'delivery_phone',
        'notes',
        'qr_code',
        'contact_name',
        'event_date',
        'event_type',
    ];

    protected $casts = [
        'event_date' => 'datetime',
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
     * The badge-cj modifier class for this order's status
     * (or an arbitrary status string, for use without an Order instance).
     */
    public function statusBadgeClass(?string $status = null): string
    {
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
        $status = $status ?? $this->status;

        if ($this->isBooking() && $status === 'delivered') {
            return 'Completed';
        }

        return ucfirst(str_replace('_', ' ', $status));
    }
}