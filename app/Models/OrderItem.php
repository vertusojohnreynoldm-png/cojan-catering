<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'package_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Nullable — set when this line item is a regular menu item.
     * Exactly one of menuItem()/package() is populated per row.
     */
    public function menuItem()
    {
        // withTrashed(): a soft-deleted menu item must still resolve here so past
        // orders keep showing the item name/price instead of a null relation.
        return $this->belongsTo(MenuItem::class)->withTrashed();
    }

    /**
     * Nullable — set when this line item is a catering package.
     * Exactly one of menuItem()/package() is populated per row.
     */
    public function package()
    {
        // withTrashed(): a soft-deleted package must still resolve here for the
        // same reason as menuItem() above — past orders keep their details.
        return $this->belongsTo(CateringPackage::class, 'package_id')->withTrashed();
    }

    /**
     * Label to show for this line item in order views, regardless of whether
     * it's a regular menu item or a catering package.
     */
    public function displayName(): string
    {
        if ($this->package_id) {
            return $this->package
                ? "{$this->package->name} ({$this->package->pax} pax package)"
                : 'Catering package';
        }

        return $this->menuItem?->name ?? 'Item';
    }
}