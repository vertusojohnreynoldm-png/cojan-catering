<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Utensil extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'quantity',
        'unit',
        'low_stock_threshold',
    ];

    public function packages()
    {
        return $this->belongsToMany(CateringPackage::class, 'package_utensils', 'utensil_id', 'package_id')
            ->withPivot('quantity_needed');
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }
}
