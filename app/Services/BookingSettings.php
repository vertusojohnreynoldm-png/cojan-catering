<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable booking rules (Admin → Booking Rules). Every rule has a
 * safe default here in code, so an empty app_settings table — a fresh
 * deploy, or the test database — behaves exactly like a configured one.
 * Values are always integers today; anything stored that doesn't parse
 * falls back to the default rather than breaking checkout.
 */
class BookingSettings
{
    private const CACHE_KEY = 'app_settings.booking';

    /**
     * key => [default, label, help, min, max]. Also drives the admin form,
     * so a new rule only needs adding here.
     */
    public const RULES = [
        'max_catering_events_per_date' => [
            'default' => 3, 'min' => 1, 'max' => 50,
            'label' => 'Max catering events per date',
            'help' => 'Once this many active bookings fall on one date, that date is greyed out for customers.',
        ],
        'max_orders_per_time_frame' => [
            'default' => 10, 'min' => 1, 'max' => 200,
            'label' => 'Max scheduled orders per delivery time frame',
            'help' => 'Applies to "Schedule for later" menu orders in each 30-minute window. ASAP orders are not counted.',
        ],
        'catering_min_lead_days' => [
            'default' => 3, 'min' => 0, 'max' => 60,
            'label' => 'Minimum catering lead time (days)',
            'help' => 'How many days ahead a catering event must be booked. 3 means the earliest bookable date is 3 days from today.',
        ],
        'catering_max_advance_months' => [
            'default' => 6, 'min' => 1, 'max' => 24,
            'label' => 'Maximum advance booking (months)',
            'help' => 'How far into the future a catering event can be booked.',
        ],
        'catering_downpayment_percent' => [
            'default' => 50, 'min' => 10, 'max' => 100,
            'label' => 'Catering downpayment (%)',
            'help' => 'Share of the package price paid upfront via GCash to secure the date. The rest is paid on the event day.',
        ],
        'payment_deadline_hours' => [
            'default' => 24, 'min' => 1, 'max' => 168,
            'label' => 'Downpayment deadline (hours)',
            'help' => 'Unpaid bookings are cancelled automatically after this many hours — or at the event start, if that comes first. A receipt waiting for review is never cancelled.',
        ],
        'max_quantity_per_menu_item' => [
            'default' => 20, 'min' => 1, 'max' => 200,
            'label' => 'Max quantity per menu item per order',
            'help' => 'Customers can\'t order more than this many of one menu item in a single order. Catering packages are not affected.',
        ],
        'max_pending_catering_bookings' => [
            'default' => 2, 'min' => 1, 'max' => 20,
            'label' => 'Max pending catering bookings per customer',
            'help' => 'A customer can\'t book another package while this many of their bookings are still Pending.',
        ],
        'max_pending_menu_orders' => [
            'default' => 3, 'min' => 1, 'max' => 20,
            'label' => 'Max pending menu orders per customer',
            'help' => 'A customer can\'t place another menu order while this many of their orders are still Pending.',
        ],
    ];

    public static function get(string $key): int
    {
        $default = self::RULES[$key]['default'];
        $stored = self::all()[$key] ?? null;

        return is_numeric($stored) ? (int) $stored : $default;
    }

    /** @return array<string, int> every rule, with defaults filled in */
    public static function values(): array
    {
        $values = [];
        foreach (array_keys(self::RULES) as $key) {
            $values[$key] = self::get($key);
        }

        return $values;
    }

    /**
     * @param  array<string, int>  $values
     * @return array<string, array{old: int, new: int}>  only what changed
     */
    public static function save(array $values): array
    {
        $before = self::values();
        $changes = [];

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::RULES)) {
                continue;
            }

            AppSetting::updateOrCreate(['key' => $key], ['value' => (string) (int) $value]);

            if ($before[$key] !== (int) $value) {
                $changes[$key] = ['old' => $before[$key], 'new' => (int) $value];
            }
        }

        Cache::forget(self::CACHE_KEY);

        return $changes;
    }

    private static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => AppSetting::pluck('value', 'key')->all());
    }
}
