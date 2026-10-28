<?php

namespace App\Models;

use App\Services\RatingStats;
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

    protected static function booted(): void
    {
        // Any change (new rating, edit, hide/unhide, delete) refreshes the
        // cached averages once it's committed.
        static::saved(fn () => RatingStats::forgetAfterCommit());
        static::deleted(fn () => RatingStats::forgetAfterCommit());
    }

    /** "Juan D." — reviews show a first name and initial, never the full name. */
    public function reviewerName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->user?->name));
        $first = $parts[0] ?? '';
        $initial = count($parts) > 1 ? ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : '';

        return $first !== '' ? $first . $initial : 'A customer';
    }

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

    /** The admin who hid it (null once un-hidden). */
    public function hider()
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    /** The admin who wrote the current reply. */
    public function replier()
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    /** Any score of 1–2 stars — overall, service, food, or any item. */
    public function scopeLow(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->where('rating', '<=', 2)
            ->orWhere('rating_service', '<=', 2)
            ->orWhere('rating_food', '<=', 2)
            ->orWhereHas('items', fn ($i) => $i->where('rating', '<=', 2)));
    }

    /** The lowest score anywhere in this rating (uses loaded items). */
    public function lowestScore(): int
    {
        return (int) collect([$this->rating, $this->rating_service, $this->rating_food])
            ->merge($this->items->pluck('rating'))
            ->filter()
            ->min();
    }

    public function isLow(): bool
    {
        return $this->lowestScore() > 0 && $this->lowestScore() <= 2;
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
