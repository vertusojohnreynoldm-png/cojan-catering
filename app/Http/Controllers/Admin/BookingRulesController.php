<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedDate;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\BookingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin → Booking Rules: the capacity / lead-time settings (one tab) and
 * blocked dates (the other). Both tabs share one view so the admin nav only
 * needs a single entry.
 */
class BookingRulesController extends Controller
{
    public function index()
    {
        return view('admin.booking-rules', [
            'tab'      => 'rules',
            'rules'    => BookingSettings::RULES,
            'values'   => BookingSettings::values(),
        ]);
    }

    public function blockedDates()
    {
        $today = Carbon::today()->toDateString();

        $upcoming = BlockedDate::where('date', '>=', $today)->orderBy('date')->get();
        $pastCount = BlockedDate::where('date', '<', $today)->count();

        // Active orders/bookings already sitting on each blocked date —
        // blocking a date never cancels them, so the admin needs to see them.
        $activeOnDate = [];
        foreach ($upcoming as $blocked) {
            $activeOnDate[$blocked->id] = $this->activeOrdersOn($blocked->day());
        }

        return view('admin.booking-rules', [
            'tab'          => 'blocked',
            'upcoming'     => $upcoming,
            'pastCount'    => $pastCount,
            'activeOnDate' => $activeOnDate,
        ]);
    }

    public function updateRules(Request $request)
    {
        $rules = [];
        foreach (BookingSettings::RULES as $key => $rule) {
            $rules[$key] = ['required', 'integer', "min:{$rule['min']}", "max:{$rule['max']}"];
        }

        $attributes = array_map(fn ($rule) => strtolower($rule['label']), BookingSettings::RULES);

        $validated = $request->validate($rules, [], $attributes);

        // The lead time has to leave at least some bookable window open.
        if ($validated['catering_min_lead_days'] >= $validated['catering_max_advance_months'] * 28) {
            return back()->withInput()->withErrors([
                'catering_min_lead_days' => 'The minimum lead time must be shorter than the maximum advance booking period.',
            ]);
        }

        $changes = BookingSettings::save($validated);

        if ($changes) {
            ActivityLogger::log(
                'settings.booking_rules_updated',
                'admin',
                auth()->user()->name . ' updated the booking rules',
                null,
                $changes
            );
        }

        return redirect()->route('admin.booking-rules')
            ->with('success', $changes ? 'Booking rules saved.' : 'No changes to save.');
    }

    public function storeBlockedDate(Request $request)
    {
        $validated = $request->validate([
            'date'   => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'unique:blocked_dates,date'],
            'reason' => ['required', 'string', 'max:150'],
        ], [
            'date.after_or_equal' => 'You can only block today or a future date.',
            'date.unique'         => 'That date is already blocked.',
        ]);

        $blocked = BlockedDate::create([
            'date'       => $validated['date'],
            'reason'     => trim($validated['reason']),
            'created_by' => auth()->id(),
        ]);

        $day = $blocked->day();

        ActivityLogger::log(
            'blocked_date.created',
            'admin',
            auth()->user()->name . " blocked {$day->format('M d, Y')} ({$blocked->reason})",
            $blocked,
            ['date' => ['old' => null, 'new' => $blocked->date], 'reason' => ['old' => null, 'new' => $blocked->reason]]
        );

        $message = "{$day->format('M d, Y')} is now blocked for all orders and bookings.";
        $existing = $this->activeOrdersOn($day);
        if ($existing > 0) {
            $message .= " Note: {$existing} active " . str('order')->plural($existing)
                . ' already on this date ' . ($existing === 1 ? 'was' : 'were') . ' NOT cancelled — review them in Orders/Bookings.';
        }

        return redirect()->route('admin.blocked-dates')->with('success', $message);
    }

    public function destroyBlockedDate($id)
    {
        $blocked = BlockedDate::findOrFail($id);
        $label = $blocked->day()->format('M d, Y');

        ActivityLogger::log(
            'blocked_date.deleted',
            'admin',
            auth()->user()->name . " unblocked {$label}",
            $blocked,
            ['date' => ['old' => $blocked->date, 'new' => null], 'reason' => ['old' => $blocked->reason, 'new' => null]]
        );

        $blocked->delete();

        return redirect()->route('admin.blocked-dates')->with('success', "{$label} is open for bookings again.");
    }

    /** Catering events on that date + menu orders scheduled for (or placed ASAP on) it. */
    private function activeOrdersOn(Carbon $day): int
    {
        $date = $day->toDateString();

        return Order::where('status', '!=', 'cancelled')
            ->where(function ($q) use ($date) {
                $q->whereDate('event_date', $date)
                    ->orWhere(fn ($q) => $q->where('delivery_type', 'scheduled')->whereDate('requested_delivery_at', $date))
                    ->orWhere(fn ($q) => $q->whereNull('event_date')->where('delivery_type', 'asap')->whereDate('created_at', $date));
            })
            ->count();
    }
}
