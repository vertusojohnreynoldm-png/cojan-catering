<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CateringPackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'pax',
        'price',
        'description',
        'image',
        'is_available',
    ];

    public function menuItems()
    {
        // Explicit pivot keys: Eloquent's default convention would expect
        // "catering_package_id" (derived from this class's name), but the
        // migration names the column "package_id".
        return $this->belongsToMany(MenuItem::class, 'package_menu_items', 'package_id', 'menu_item_id')
            ->withPivot('quantity');
    }

    public function utensils()
    {
        return $this->belongsToMany(Utensil::class, 'package_utensils', 'package_id', 'utensil_id')
            ->withPivot('quantity_needed');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'package_id');
    }
}
