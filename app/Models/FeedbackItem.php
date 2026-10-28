<?php

namespace App\Models;

use App\Services\RatingStats;
use Illuminate\Database\Eloquent\Model;

/** Stars (and an optional comment) for one line of a rated order. */
class FeedbackItem extends Model
{
    protected $fillable = [
        'feedback_id',
        'order_item_id',
        'menu_item_id',
        'package_id',
        'rating',
        'comment',
    ];

    protected $casts = ['rating' => 'integer'];

    protected static function booted(): void
    {
        static::saved(fn () => RatingStats::forgetAfterCommit());
        static::deleted(fn () => RatingStats::forgetAfterCommit());
    }

    public function feedback()
    {
        return $this->belongsTo(Feedback::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function package()
    {
        return $this->belongsTo(CateringPackage::class, 'package_id');
    }
}
