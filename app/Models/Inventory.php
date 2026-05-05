<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_item_id',
        'quantity',
        'low_stock_threshold',
        'unit',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }
}