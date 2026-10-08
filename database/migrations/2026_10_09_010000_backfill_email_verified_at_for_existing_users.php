<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MustVerifyEmail is new as of this migration — every account that
     * already existed registered before email verification existed at all,
     * so none of them should suddenly be locked out of ordering. Each gets
     * their own created_at as the verified timestamp (more honest than
     * stamping "now" on a six-month-old account).
     */
    public function up(): void
    {
        DB::statement('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL');
    }

    public function down(): void
    {
        // Intentionally a no-op — reversing this would re-lock existing
        // accounts out of ordering, which is never what a rollback should do.
    }
};
