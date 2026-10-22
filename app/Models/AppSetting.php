<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw key/value row — always read and written through
 * App\Services\BookingSettings, which owns the defaults, casting and cache.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];
}
