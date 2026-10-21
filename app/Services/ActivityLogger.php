<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

class ActivityLogger
{
    // Set while PackageStockService (and anything else doing a bulk,
    // side-effect stock adjustment on behalf of a bigger action) is running,
    // so per-row Observer logging doesn't fire a flood of individual entries
    // for what is really one customer action. The caller logs its own single
    // consolidated entry instead — see OrderController::store()'s order.placed
    // entry and PackageStockService::lockAndDecrement().
    private static bool $suppressed = false;

    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$suppressed;
        self::$suppressed = true;

        try {
            return $callback();
        } finally {
            self::$suppressed = $previous;
        }
    }

    /**
     * @param  string  $action    e.g. 'order.status_changed'
     * @param  string  $category  'auth' | 'orders' | 'admin' | 'stock'
     * @param  string  $description  Already-formatted human sentence.
     * @param  \Illuminate\Database\Eloquent\Model|null  $subject
     * @param  array|null  $changes  ['field' => ['old' => ..., 'new' => ...], ...]
     * @param  \App\Models\User|null  $actor  Defaults to the authenticated user;
     *         falls back to a "System" snapshot if there is none.
     */
    public static function log(
        string $action,
        string $category,
        string $description,
        ?object $subject = null,
        ?array $changes = null,
        ?User $actor = null,
    ): void {
        if (self::$suppressed) {
            return;
        }

        // A logging failure must never break the real action it's attached
        // to — this is a side effect, not a dependency of the request.
        try {
            $actor = $actor ?? auth()->user();

            ActivityLog::create([
                'actor_id'      => $actor?->id,
                'actor_name'    => $actor?->name ?? 'System',
                'actor_role'    => $actor?->role ?? 'system',
                'action'        => $action,
                'category'      => $category,
                'description'   => $description,
                'subject_type'  => $subject ? class_basename($subject) : null,
                'subject_id'    => $subject->id ?? null,
                'subject_label' => $subject ? self::labelFor($subject) : null,
                'changes'       => $changes,
                'ip_address'    => Request::ip(),
                'user_agent'    => self::summarizeUserAgent(Request::userAgent()),
            ]);
        } catch (Throwable $e) {
            Log::error('ActivityLogger failed to write a log entry', [
                'action' => $action,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Logs an entry for a failed login, capped to once per IP+email per
     * $windowSeconds so a brute-force attempt doesn't flood the table with
     * one row per try — only the first attempt in a window is recorded.
     */
    public static function logFailedLogin(string $email, int $windowSeconds = 300): void
    {
        $ip = Request::ip();
        $cacheKey = 'activity_log:failed_login:' . $ip . ':' . $email;

        try {
            if (Cache::has($cacheKey)) {
                return;
            }
            Cache::put($cacheKey, true, $windowSeconds);
        } catch (Throwable $e) {
            // If the cache itself is unavailable, fall through and log
            // anyway — missing the dedupe window is better than silently
            // dropping a security-relevant event entirely.
        }

        self::log(
            'auth.failed_login',
            'auth',
            "Failed login attempt for {$email}",
            actor: null,
        );
    }

    /**
     * Reduces a model's getChanges() to only the fields worth an audit-log
     * entry: drops timestamps and any model-specific ignore list (GPS pings,
     * generated QR payloads, etc.), and returns null (meaning "don't log
     * this save") once nothing meaningful is left.
     *
     * @return array<string, array{old: mixed, new: mixed}>|null
     */
    public static function diffFor($model, array $ignore = []): ?array
    {
        $ignore = array_merge(['created_at', 'updated_at'], $ignore);
        $changed = array_diff(array_keys($model->getChanges()), $ignore);

        if (empty($changed)) {
            return null;
        }

        $original = $model->getOriginal();
        $diff = [];

        foreach ($changed as $field) {
            $old = $original[$field] ?? null;
            $new = $model->getAttribute($field);

            // A boolean-style field (is_available, etc.) commonly ends up
            // with mismatched representations on each side — the DB-loaded
            // original comes back as 0/1, while a freshly-set PHP `false`
            // stays a real boolean until the model is reloaded. {{ false }}
            // renders as nothing in Blade, so without this an entry like
            // "is_available: 1 →" silently loses its new value.
            //
            // The trigger is deliberately narrow: only when one side is a
            // genuine PHP bool (is_bool(), not == 0/1) — an actual int field
            // like user_id or quantity can legitimately hold 0 or 1 and must
            // never be coerced into "Yes"/"No".
            if (is_bool($old) || is_bool($new)) {
                $old = ((bool) $old) ? 'Yes' : 'No';
                $new = ((bool) $new) ? 'Yes' : 'No';
            }

            $diff[$field] = ['old' => $old, 'new' => $new];
        }

        return $diff;
    }

    private static function labelFor($subject): ?string
    {
        return match (true) {
            isset($subject->order_number)        => 'Order #' . $subject->order_number,
            $subject instanceof \App\Models\Inventory => $subject->menuItem?->name ?? ('Inventory #' . $subject->id),
            isset($subject->name)                => $subject->name,
            default                              => class_basename($subject) . ' #' . ($subject->id ?? '?'),
        };
    }

    /**
     * A short "Browser on OS" summary — not a full UA-parser dependency,
     * just enough to be useful in an audit entry without storing the raw
     * (often long, always noisy) user-agent string.
     */
    public static function summarizeUserAgent(?string $ua): ?string
    {
        if (!$ua) {
            return null;
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/')      => 'Edge',
            str_contains($ua, 'OPR/')      => 'Opera',
            str_contains($ua, 'Chrome/')   => 'Chrome',
            str_contains($ua, 'CriOS/')    => 'Chrome',
            str_contains($ua, 'Firefox/')  => 'Firefox',
            str_contains($ua, 'FxiOS/')    => 'Firefox',
            str_contains($ua, 'Safari/') && str_contains($ua, 'Version/') => 'Safari',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows')        => 'Windows',
            str_contains($ua, 'Android')        => 'Android',
            str_contains($ua, 'iPhone'),
            str_contains($ua, 'iPad'),
            str_contains($ua, 'iOS')            => 'iOS',
            str_contains($ua, 'Macintosh')      => 'Mac',
            str_contains($ua, 'Linux')          => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
