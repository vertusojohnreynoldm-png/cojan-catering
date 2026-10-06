<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'last_location_update',
    ];

    protected $casts = [
        'assigned_at'           => 'datetime',
        'picked_up_at'          => 'datetime',
        'delivered_at'          => 'datetime',
        'last_location_update'  => 'datetime',
        'current_lat'           => 'float',
        'current_lng'           => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function personnel()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}