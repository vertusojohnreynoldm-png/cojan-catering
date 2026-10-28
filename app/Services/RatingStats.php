<?php

namespace App\Services;

use App\Models\FeedbackItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Average stars and rating counts per menu item and per package, from
 * VISIBLE reviews only (a hidden review never counts). One grouped query
 * covers every item and package, cached so the menu page stays a single
 * cache read; Feedback / FeedbackItem clear it after any change commits
 * (see their booted()), and it expires within the hour regardless.
 */
class RatingStats
{
    private const KEY = 'ratings.stats';

    /** @return array{menu: array<int, array{avg: float, count: int}>, package: array<int, array{avg: float, count: int}>} */
    public static function all(): array
    {
        return Cache::remember(self::KEY, now()->addHour(), function () {
            $rows = DB::table('feedback_items')
                ->join('feedback', 'feedback.id', '=', 'feedback_items.feedback_id')
                ->where('feedback.is_hidden', false)
                ->selectRaw('feedback_items.menu_item_id, feedback_items.package_id, AVG(feedback_items.rating) AS avg_rating, COUNT(*) AS total')
                ->groupBy('feedback_items.menu_item_id', 'feedback_items.package_id')
                ->get();

            $stats = ['menu' => [], 'package' => []];
            foreach ($rows as $row) {
                $entry = ['avg' => round((float) $row->avg_rating, 1), 'count' => (int) $row->total];
                if ($row->menu_item_id) {
                    $stats['menu'][(int) $row->menu_item_id] = $entry;
                } elseif ($row->package_id) {
                    $stats['package'][(int) $row->package_id] = $entry;
                }
            }

            return $stats;
        });
    }

    public static function forMenuItem(int $id): ?array
    {
        return self::all()['menu'][$id] ?? null;
    }

    public static function forPackage(int $id): ?array
    {
        return self::all()['package'][$id] ?? null;
    }

    /**
     * Recent visible reviews of one menu item ('menu_item_id') or package
     * ('package_id'), newest first, with the reviewer and any reply.
     */
    public static function reviews(string $column, int $id, int $perPage = 6): LengthAwarePaginator
    {
        return FeedbackItem::with('feedback.user')
            ->where($column, $id)
            ->whereHas('feedback', fn ($q) => $q->visible())
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /** @return array<int, int>  stars (5..1) => how many visible ratings gave that */
    public static function breakdown(string $column, int $id): array
    {
        $counts = FeedbackItem::where($column, $id)
            ->whereHas('feedback', fn ($q) => $q->visible())
            ->selectRaw('rating, COUNT(*) AS total')->groupBy('rating')
            ->pluck('total', 'rating');

        $breakdown = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $breakdown[$stars] = (int) ($counts[$stars] ?? 0);
        }

        return $breakdown;
    }

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /** Clear the cache once the current transaction (if any) commits. */
    public static function forgetAfterCommit(): void
    {
        DB::afterCommit(fn () => self::forget());
    }
}
