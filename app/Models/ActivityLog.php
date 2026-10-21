<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    // Append-only audit trail — no updated_at (see migration), and this
    // model is never updated or deleted through the app (read-only in the
    // UI, per the brief).
    const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'action',
        'category',
        'description',
        'subject_type',
        'subject_id',
        'subject_label',
        'changes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Best-effort live link to the actor — intentionally has no FK, so this
     * simply resolves to null if the user was since deleted. Display code
     * should always prefer actor_name/actor_role (the snapshot) over this.
     */
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
