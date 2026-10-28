<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Ratings;
use App\Services\RatingStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Admin → Ratings (formerly Feedback): every customer rating with filters,
 * averages per item / package / rider, replies the customer sees, and
 * hide/unhide with a required reason. Every moderation step is logged.
 */
class RatingController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'item' => ['nullable', 'integer'],
            'package' => ['nullable', 'integer'],
            'rider' => ['nullable', 'integer'],
            'stars' => ['nullable', 'in:1,2,3,4,5,low'],
            'visibility' => ['nullable', 'in:visible,hidden'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Feedback::with('user', 'order', 'items.menuItem', 'items.package', 'rider', 'hider', 'replier')->latest();

        if (!empty($filters['item'])) {
            $query->whereHas('items', fn ($q) => $q->where('menu_item_id', $filters['item']));
        }
        if (!empty($filters['package'])) {
            $query->whereHas('items', fn ($q) => $q->where('package_id', $filters['package']));
        }
        if (!empty($filters['rider'])) {
            $query->where('rider_id', $filters['rider']);
        }
        if (($filters['stars'] ?? null) === 'low') {
            $query->low();
        } elseif (!empty($filters['stars'])) {
            $query->where('rating', (int) $filters['stars']); // overall score
        }
        if (($filters['visibility'] ?? null) === 'hidden') {
            $query->where('is_hidden', true);
        } elseif (($filters['visibility'] ?? null) === 'visible') {
            $query->visible();
        }
        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $ratings = $query->paginate(15)->withQueryString();

        // Averages: all-time, visible ratings only (same numbers customers see).
        $stats = RatingStats::all();
        $itemNames = MenuItem::whereIn('id', array_keys($stats['menu']))->pluck('name', 'id');
        $packageNames = CateringPackage::whereIn('id', array_keys($stats['package']))->pluck('name', 'id');
        $itemAverages = $this->named($stats['menu'], $itemNames);
        $packageAverages = $this->named($stats['package'], $packageNames);

        $riderRows = Feedback::visible()->whereNotNull('rider_id')->whereNotNull('rating_service')
            ->selectRaw('rider_id, AVG(rating_service) AS avg_rating, COUNT(*) AS total')
            ->groupBy('rider_id')->get();
        $riderNames = User::whereIn('id', $riderRows->pluck('rider_id'))->pluck('name', 'id');
        $riderAverages = $riderRows->map(fn ($row) => [
            'id' => (int) $row->rider_id,
            'name' => $riderNames[$row->rider_id] ?? 'Former rider',
            'avg' => round((float) $row->avg_rating, 1),
            'count' => (int) $row->total,
        ])->sortByDesc('count')->values();

        $summary = [
            'total' => Feedback::count(),
            'average' => round((float) Feedback::visible()->avg('rating'), 1),
            'hidden' => Feedback::where('is_hidden', true)->count(),
            'low30' => Feedback::low()->where('created_at', '>=', now()->subDays(30))->count(),
        ];

        return view('admin.ratings', [
            'ratings' => $ratings,
            'filters' => $filters,
            'summary' => $summary,
            'itemAverages' => $itemAverages,
            'packageAverages' => $packageAverages,
            'riderAverages' => $riderAverages,
            'menuItems' => MenuItem::orderBy('name')->get(['id', 'name']),
            'packages' => CateringPackage::orderBy('name')->get(['id', 'name']),
            'riders' => User::where('role', 'delivery')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Save, change or (empty text) remove the reply the customer sees. */
    public function reply(Request $request, Feedback $feedback)
    {
        $request->merge(['reply' => Ratings::clean(is_string($request->input('reply')) ? $request->input('reply') : null)]);
        $validated = $request->validate(['reply' => ['nullable', 'string', 'max:1000']], [
            'reply.max' => 'Please keep the reply under 1000 characters.',
        ]);

        $old = $feedback->admin_reply;
        $new = $validated['reply'] ?? null;
        if ($old === $new) {
            return $this->back($request, $feedback, 'success', 'No changes to the reply.');
        }

        $feedback->update([
            'admin_reply' => $new,
            'replied_at' => $new ? now() : null,
            'replied_by' => $new ? $request->user()->id : null,
        ]);

        [$action, $verb] = match (true) {
            $new === null => ['rating.reply_removed', 'removed the reply to'],
            $old === null => ['rating.replied', 'replied to'],
            default => ['rating.reply_updated', 'edited the reply to'],
        };
        ActivityLogger::log(
            $action,
            'admin',
            "{$request->user()->name} {$verb} {$feedback->user?->name}'s rating on Order #{$feedback->order?->order_number}",
            subject: $feedback->order,
            changes: ['admin_reply' => ['old' => $old, 'new' => $new]],
        );

        return $this->back($request, $feedback, 'success', $new ? 'Reply saved — the customer will see it on their order.' : 'Reply removed.');
    }

    public function hide(Request $request, Feedback $feedback)
    {
        $reason = $this->reason($request, 'hiding');

        if (!$feedback->is_hidden) {
            $feedback->update(['is_hidden' => true, 'hidden_reason' => $reason, 'hidden_at' => now(), 'hidden_by' => $request->user()->id]);
            ActivityLogger::log(
                'rating.hidden',
                'admin',
                "{$request->user()->name} hid {$feedback->user?->name}'s rating on Order #{$feedback->order?->order_number}: {$reason}",
                subject: $feedback->order,
                changes: ['is_hidden' => ['old' => false, 'new' => true], 'reason' => ['old' => null, 'new' => $reason]],
            );
        }

        return $this->back($request, $feedback, 'success', 'Rating hidden. It no longer shows to customers or counts in averages.');
    }

    public function unhide(Request $request, Feedback $feedback)
    {
        $reason = $this->reason($request, 'showing it again');

        if ($feedback->is_hidden) {
            $previous = $feedback->hidden_reason;
            $feedback->update(['is_hidden' => false, 'hidden_reason' => null, 'hidden_at' => null, 'hidden_by' => null]);
            ActivityLogger::log(
                'rating.unhidden',
                'admin',
                "{$request->user()->name} un-hid {$feedback->user?->name}'s rating on Order #{$feedback->order?->order_number}: {$reason}",
                subject: $feedback->order,
                changes: ['is_hidden' => ['old' => true, 'new' => false], 'reason' => ['old' => $previous, 'new' => $reason]],
            );
        }

        return $this->back($request, $feedback, 'success', 'Rating is visible again.');
    }

    private function reason(Request $request, string $doing): string
    {
        $request->merge(['reason' => Ratings::clean(is_string($request->input('reason')) ? $request->input('reason') : null)]);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => "Please give a reason for {$doing} — it's kept in the activity log.",
            'reason.max' => 'Please keep the reason under 255 characters.',
        ]);

        return $validated['reason'];
    }

    /** Back to the same filtered list, scrolled to this rating. */
    private function back(Request $request, Feedback $feedback, string $type, string $message)
    {
        $to = $request->input('return') && Str::startsWith($request->input('return'), route('admin.ratings'))
            ? $request->input('return')
            : route('admin.ratings');

        return redirect()->to($to . '#rating-' . $feedback->id)->with($type, $message);
    }

    /** @return \Illuminate\Support\Collection<int, array{id:int,name:string,avg:float,count:int}> */
    private function named(array $stats, $names)
    {
        return collect($stats)
            ->map(fn ($s, $id) => ['id' => $id, 'name' => $names[$id] ?? 'Removed item'] + $s)
            ->sortByDesc('count')->values();
    }
}
