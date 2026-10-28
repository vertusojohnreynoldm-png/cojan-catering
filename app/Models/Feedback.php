<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer's rating of one order. `rating` is the overall experience;
 * rating_service / rating_food mean different things for menu orders and
 * catering bookings (see serviceLabels()). Per-item stars live in
 * FeedbackItem. Hidden reviews never count toward any average.
 */
class Feedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'rating',
        'rating_service',
        'rating_food',
        'rider_id',
        'comment',
        'photo_path',
        'edited_at',
        'is_hidden',
        'hidden_reason',
        'hidden_at',
        'hidden_by',
        'admin_reply',
        'replied_at',
        'replied_by',
    ];

    protected $casts = [
        'rating' => 'integer',
        'rating_service' => 'integer',
        'rating_food' => 'integer',
        'is_hidden' => 'boolean',
        'edited_at' => 'datetime',
        'hidden_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(FeedbackItem::class);
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Labels for the three service scores. Menu orders without a rider have
     * no service score (null), so it's left out.
     *
     * @return array<string, string>  column => label
     */
    public static function serviceLabels(bool $isBooking): array
    {
        return $isBooking
            ? ['rating_service' => 'Setup & staff', 'rating_food' => 'Food quality', 'rating' => 'Overall experience']
            : ['rating_service' => 'Delivery & rider', 'rating_food' => 'Packaging & food condition', 'rating' => 'Overall experience'];
    }
}
