<?php

namespace App\Models;

use App\Observers\DeliveryObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(DeliveryObserver::class)]
class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'notes',
        'current_lat',
        'current_lng',
        'current_accuracy',
        'current_heading',
        'current_speed',
        'last_location_update',
        'proof_photo_path',
        'received_by',
        'delivery_note',
        'proof_lat',
        'proof_lng',
        'proof_accuracy',
        'customer_confirmed_at',
        'qr_scanned_at',
        'qr_scanned_by',
        'qr_scan_lat',
        'qr_scan_lng',
        'qr_scan_accuracy',
    ];

    protected $casts = [
        'assigned_at'           => 'datetime',
        'picked_up_at'          => 'datetime',
        'delivered_at'          => 'datetime',
        'last_location_update'  => 'datetime',
        'current_lat'           => 'float',
        'current_lng'           => 'float',
        'current_accuracy'      => 'float',
        'proof_lat'             => 'float',
        'proof_lng'             => 'float',
        'proof_accuracy'        => 'float',
        'customer_confirmed_at' => 'datetime',
        'qr_scanned_at'         => 'datetime',
        'qr_scan_lat'           => 'float',
        'qr_scan_lng'           => 'float',
        'qr_scan_accuracy'      => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * True while the assigned rider may see the customer's location and
     * route to it: a MENU order (catering bookings never get the rider map),
     * the delivery not yet finished, and the order not cancelled/delivered.
     * Once this is false the coordinates are neither rendered nor served.
     */
    public function navigationActive(): bool
    {
        $order = $this->order;

        return $order !== null
            && !$order->isBooking()
            && in_array($this->status, ['assigned', 'picked_up', 'in_transit'], true)
            && !in_array($order->status, ['cancelled', 'delivered'], true);
    }

    public function personnel()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function qrScanner()
    {
        return $this->belongsTo(User::class, 'qr_scanned_by');
    }

    /** The assigned rider scanned the customer's order QR at hand-off. */
    public function qrVerified(): bool
    {
        return $this->qr_scanned_at !== null;
    }

    /** A menu order completed without the (optional) QR hand-off scan. */
    public function deliveredWithoutQr(): bool
    {
        return $this->status === 'delivered' && !$this->qrVerified() && !$this->order?->isBooking();
    }

    public function hasProofOfDelivery(): bool
    {
        return $this->status === 'delivered' && !empty($this->proof_photo_path);
    }
}