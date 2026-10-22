<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A whole day on which nothing can be booked — no catering events, no
 * scheduled menu deliveries and no ASAP orders. `date` is deliberately left
 * uncast so it stays a plain 'Y-m-d' string for equality lookups on both
 * MySQL and SQLite.
 */
class BlockedDate extends Model
{
    protected $fillable = ['date', 'reason', 'created_by'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function day(): Carbon
    {
        return Carbon::parse($this->date);
    }
}
