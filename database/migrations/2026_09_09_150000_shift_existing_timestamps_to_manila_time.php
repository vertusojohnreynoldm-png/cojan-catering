<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * One-time backfill, run atomically with the APP_TIMEZONE=UTC -> Asia/Manila
     * config change (see config/app.php and .env).
     *
     * Every existing datetime/timestamp value in this database was written
     * while the app's timezone was UTC. Laravel/Carbon uses config('app.timezone')
     * for BOTH writing new values AND interpreting raw values already in the
     * database — it does not store "true" UTC internally and convert for
     * display. So flipping the config alone would leave every existing row
     * exactly as wrong as it is today: instead of being read as UTC (correct,
     * 8 hours behind real Philippine time), it would be misread as if it were
     * already Manila-local (still 8 hours behind, just for a different reason).
     * Shifting every existing value by +8 hours here, in the same deploy step
     * as the config change, makes old data consistent with how it will be
     * interpreted from now on, so there is no window where config and data
     * disagree in either direction.
     *
     * NULL-valued columns are left as NULL automatically — MySQL's date
     * arithmetic evaluates `NULL + INTERVAL 8 HOUR` as NULL, so nullable
     * columns (deleted_at, email_verified_at, assigned_at, etc.) need no
     * special-casing.
     *
     * Deliberately NOT touched: cache/cache_locks.expiration, jobs' and
     * job_batches' timestamp columns, sessions.last_activity — all confirmed
     * to be integer Unix timestamps (via INFORMATION_SCHEMA), not DATETIME/
     * TIMESTAMP columns, and therefore already timezone-agnostic; shifting
     * them would break them, not fix them. Confirmed via SHOW CREATE TABLE
     * that none of the columns touched below carry an ON UPDATE CURRENT_
     * TIMESTAMP clause that could silently overwrite this backfill.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $this->shift('categories', ['created_at', 'updated_at'], 8);
            $this->shift('catering_packages', ['created_at', 'updated_at', 'deleted_at'], 8);
            $this->shift('deliveries', ['created_at', 'updated_at', 'assigned_at', 'picked_up_at', 'delivered_at'], 8);
            $this->shift('failed_jobs', ['failed_at'], 8);
            $this->shift('feedback', ['created_at', 'updated_at'], 8);
            $this->shift('inventories', ['created_at', 'updated_at'], 8);
            $this->shift('menu_items', ['created_at', 'updated_at', 'deleted_at'], 8);
            $this->shift('messages', ['created_at', 'updated_at'], 8);
            $this->shift('notifications', ['created_at', 'updated_at'], 8);
            $this->shift('order_items', ['created_at', 'updated_at'], 8);
            $this->shift('orders', ['created_at', 'updated_at'], 8);
            $this->shift('package_menu_items', ['created_at', 'updated_at'], 8);
            $this->shift('package_utensils', ['created_at', 'updated_at'], 8);
            $this->shift('password_reset_tokens', ['created_at'], 8);
            $this->shift('users', ['created_at', 'updated_at', 'email_verified_at'], 8);
            $this->shift('utensils', ['created_at', 'updated_at'], 8);
        });
    }

    /**
     * Reverses the shift. Only meaningful if paired with reverting
     * APP_TIMEZONE back to UTC in the same step — running this alone would
     * put the data back out of sync with a still-Manila config.
     */
    public function down(): void
    {
        DB::transaction(function () {
            $this->shift('categories', ['created_at', 'updated_at'], -8);
            $this->shift('catering_packages', ['created_at', 'updated_at', 'deleted_at'], -8);
            $this->shift('deliveries', ['created_at', 'updated_at', 'assigned_at', 'picked_up_at', 'delivered_at'], -8);
            $this->shift('failed_jobs', ['failed_at'], -8);
            $this->shift('feedback', ['created_at', 'updated_at'], -8);
            $this->shift('inventories', ['created_at', 'updated_at'], -8);
            $this->shift('menu_items', ['created_at', 'updated_at', 'deleted_at'], -8);
            $this->shift('messages', ['created_at', 'updated_at'], -8);
            $this->shift('notifications', ['created_at', 'updated_at'], -8);
            $this->shift('order_items', ['created_at', 'updated_at'], -8);
            $this->shift('orders', ['created_at', 'updated_at'], -8);
            $this->shift('package_menu_items', ['created_at', 'updated_at'], -8);
            $this->shift('package_utensils', ['created_at', 'updated_at'], -8);
            $this->shift('password_reset_tokens', ['created_at'], -8);
            $this->shift('users', ['created_at', 'updated_at', 'email_verified_at'], -8);
            $this->shift('utensils', ['created_at', 'updated_at'], -8);
        });
    }

    private function shift(string $table, array $columns, int $hours): void
    {
        $sign = $hours >= 0 ? '+' : '-';
        $abs = abs($hours);
        $assignments = collect($columns)
            ->map(fn ($col) => "`{$col}` = `{$col}` {$sign} INTERVAL {$abs} HOUR")
            ->implode(', ');

        DB::statement("UPDATE `{$table}` SET {$assignments}");
    }
};
