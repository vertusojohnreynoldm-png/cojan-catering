<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BlockedDate;
use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\BookingCapacity;
use App\Services\BookingSettings;
use App\Services\DeliverySlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage 1 of the fake/double-booking work: admin-editable capacity limits,
// blocked dates, the lead-time window, the same-customer duplicate rules,
// and the row lock BookingCapacity takes inside the order transaction.
// Time is frozen to a Wednesday inside business hours (same as
// DeliveryTimeFrameTest) so menu-order slots exist.
class BookingCapacityTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila');
        Carbon::setTestNow($this->now);
    }

    // ---- helpers ----------------------------------------------------------

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function package(): CateringPackage
    {
        return CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => 2000, 'is_available' => true]);
    }

    /** An existing order row, inserted directly (bypassing the flow under test). */
    private function existingOrder(array $attributes): Order
    {
        return Order::create(array_merge([
            'user_id'          => $this->customer()->id,
            'order_number'     => 'ORD-' . strtoupper(Str::random(8)),
            'status'           => 'pending',
            'payment_method'   => 'cash_on_delivery',
            'payment_status'   => 'unpaid',
            'subtotal'         => 100,
            'delivery_fee'     => 0,
            'total_amount'     => 100,
            'delivery_address' => 'Somewhere',
            'delivery_phone'   => '09171234567',
        ], $attributes));
    }

    private function existingBookingOn(string $date, array $attributes = []): Order
    {
        return $this->existingOrder(array_merge(['event_date' => "$date 15:00:00"], $attributes));
    }

    private function availPayload(CateringPackage $package, string $eventDate): array
    {
        return [
            'package_id'     => $package->id,
            'contact_name'   => 'Juan Cruz',
            'contact_number' => '09171234567',
            'province'       => 'Occidental Mindoro',
            'municipality'   => 'San Jose',
            'barangay'       => 'Caminawit',
            'street'         => '123 Test St',
            'event_date'     => $eventDate,
        ];
    }

    private function withMenuCart(): void
    {
        $category = Category::create(['name' => 'Mains']);
        $item = MenuItem::create([
            'category_id' => $category->id, 'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true,
        ]);

        session(['cart' => [
            'menu_' . $item->id => ['id' => $item->id, 'name' => $item->name, 'price' => 150, 'quantity' => 1, 'type' => 'menu'],
        ]]);
    }

    private function menuOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'province'       => 'Occidental Mindoro',
            'municipality'   => 'San Jose',
            'barangay'       => 'Caminawit',
            'street'         => '123 Test St',
            'delivery_phone' => '09171234567',
            'payment_method' => 'cash_on_delivery',
            'delivery_type'  => 'asap',
        ], $overrides);
    }

    private function firstSlot(): array
    {
        $slots = DeliverySlots::slotsFor($this->now->toDateString());
        $this->assertNotEmpty($slots, 'Precondition: a slot must exist today.');

        return $slots[0];
    }

    private function scheduledOrderIn(array $slot, array $attributes = []): Order
    {
        return $this->existingOrder(array_merge([
            'delivery_type'         => 'scheduled',
            'requested_delivery_at' => Carbon::parse($slot['value']),
            'delivery_window_label' => $slot['label'],
        ], $attributes));
    }

    // ---- settings ---------------------------------------------------------

    public function test_settings_fall_back_to_safe_defaults_when_never_saved(): void
    {
        $this->assertSame(3, BookingSettings::get('max_catering_events_per_date'));
        $this->assertSame(10, BookingSettings::get('max_orders_per_time_frame'));
        $this->assertSame(3, BookingSettings::get('catering_min_lead_days'));
        $this->assertSame(6, BookingSettings::get('catering_max_advance_months'));
    }

    public function test_admin_can_update_booking_rules_and_it_is_logged(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/booking-rules', [
            'max_catering_events_per_date' => 5,
            'max_orders_per_time_frame'    => 8,
            'catering_min_lead_days'       => 2,
            'catering_max_advance_months'  => 12,
            'max_pending_catering_bookings' => 2,
            'max_pending_menu_orders'      => 3,
            'catering_downpayment_percent' => 50,
            'payment_deadline_hours'       => 24,
            'max_quantity_per_menu_item'   => 20,
            'rating_window_days'           => 30,
            'rating_edit_days'             => 7,
        ])->assertRedirect(route('admin.booking-rules'));

        $this->assertSame(5, BookingSettings::get('max_catering_events_per_date'));
        $this->assertSame(8, BookingSettings::get('max_orders_per_time_frame'));

        $log = ActivityLog::where('action', 'settings.booking_rules_updated')->first();
        $this->assertNotNull($log);
        $this->assertSame(['old' => 3, 'new' => 5], $log->changes['max_catering_events_per_date']);
    }

    public function test_booking_rules_reject_out_of_range_values(): void
    {
        $this->actingAs($this->admin())->post('/admin/booking-rules', [
            'max_catering_events_per_date' => 0,
            'max_orders_per_time_frame'    => 10,
            'catering_min_lead_days'       => 3,
            'catering_max_advance_months'  => 6,
            'max_pending_catering_bookings' => 2,
            'max_pending_menu_orders'      => 3,
            'catering_downpayment_percent' => 50,
            'payment_deadline_hours'       => 24,
            'max_quantity_per_menu_item'   => 20,
            'rating_window_days'           => 30,
            'rating_edit_days'             => 7,
        ])->assertSessionHasErrors('max_catering_events_per_date');

        $this->assertSame(3, BookingSettings::get('max_catering_events_per_date'));
    }

    public function test_customers_cannot_reach_booking_rules(): void
    {
        $this->actingAs($this->customer())->get('/admin/booking-rules')->assertRedirect(route('customer.dashboard'));
        $this->actingAs($this->customer())->post('/admin/blocked-dates', ['date' => '2026-11-20', 'reason' => 'x']);
        $this->assertSame(0, BlockedDate::count());
    }

    public function test_admin_booking_rules_pages_render(): void
    {
        $admin = $this->admin();
        BlockedDate::create(['date' => '2026-11-20', 'reason' => 'Town fiesta']);

        $this->actingAs($admin)->get('/admin/booking-rules')->assertOk()->assertSee('Max catering events per date');
        $this->actingAs($admin)->get('/admin/blocked-dates')->assertOk()->assertSee('Town fiesta');
    }

    // ---- blocked dates (admin) -------------------------------------------

    public function test_admin_can_block_and_unblock_a_date_with_logging(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/blocked-dates', ['date' => '2026-11-20', 'reason' => 'Town fiesta'])
            ->assertRedirect(route('admin.blocked-dates'));

        $blocked = BlockedDate::first();
        $this->assertSame('Town fiesta', $blocked->reason);
        $this->assertTrue(ActivityLog::where('action', 'blocked_date.created')->exists());

        $this->actingAs($admin)->delete("/admin/blocked-dates/{$blocked->id}")->assertRedirect(route('admin.blocked-dates'));

        $this->assertSame(0, BlockedDate::count());
        $this->assertTrue(ActivityLog::where('action', 'blocked_date.deleted')->exists());
    }

    public function test_admin_cannot_block_a_past_or_already_blocked_date(): void
    {
        $admin = $this->admin();
        BlockedDate::create(['date' => '2026-11-20', 'reason' => 'Fiesta']);

        $this->actingAs($admin)->post('/admin/blocked-dates', ['date' => '2026-11-01', 'reason' => 'Past'])
            ->assertSessionHasErrors('date');
        $this->actingAs($admin)->post('/admin/blocked-dates', ['date' => '2026-11-20', 'reason' => 'Again'])
            ->assertSessionHasErrors('date');
        $this->actingAs($admin)->post('/admin/blocked-dates', ['date' => '2026-11-21'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(1, BlockedDate::count());
    }

    // ---- catering: capacity, blocked, lead time ---------------------------

    public function test_catering_date_at_capacity_is_rejected(): void
    {
        $package = $this->package();
        foreach (range(1, 3) as $i) {
            $this->existingBookingOn('2026-11-20');
        }

        $this->actingAs($this->customer())
            ->post('/customer/packages/avail', $this->availPayload($package, '2026-11-20 15:00'))
            ->assertSessionHasErrors(['event_date' => 'Nov 20, 2026 is fully booked. Please choose another date.']);
    }

    public function test_cancelled_bookings_do_not_count_toward_capacity(): void
    {
        $package = $this->package();
        $this->existingBookingOn('2026-11-20');
        $this->existingBookingOn('2026-11-20');
        $this->existingBookingOn('2026-11-20', ['status' => 'cancelled']);

        $this->actingAs($this->customer())
            ->post('/customer/packages/avail', $this->availPayload($package, '2026-11-20 15:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_capacity_follows_the_admin_setting(): void
    {
        BookingSettings::save(['max_catering_events_per_date' => 1]);
        $package = $this->package();
        $this->existingBookingOn('2026-11-20');

        $this->actingAs($this->customer())
            ->post('/customer/packages/avail', $this->availPayload($package, '2026-11-20 15:00'))
            ->assertSessionHasErrors('event_date');
    }

    public function test_capacity_is_rechecked_at_final_confirmation(): void
    {
        // Date had room when the customer filled in the Avail modal, but
        // filled up before they confirmed — the final store() must catch it.
        $package = $this->package();
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post('/customer/packages/avail', $this->availPayload($package, '2026-11-20 15:00'))
            ->assertSessionHasNoErrors();

        foreach (range(1, 3) as $i) {
            $this->existingBookingOn('2026-11-20');
        }

        $this->actingAs($customer)
            ->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery'])
            ->assertRedirect(route('customer.packages.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Order::where('user_id', $customer->id)->count());
    }

    public function test_last_catering_slot_goes_to_only_one_of_two_customers(): void
    {
        BookingSettings::save(['max_catering_events_per_date' => 1]);
        $package = $this->package();
        $first = $this->customer();
        $second = $this->customer();

        // Both pass step 1 while the date is still open...
        foreach ([$first, $second] as $customer) {
            $this->actingAs($customer)
                ->post('/customer/packages/avail', $this->availPayload($package, '2026-11-20 15:00'))
                ->assertSessionHasNoErrors();
        }

        // ...but only the first confirmation can take the last place.
        $this->actingAs($first)->withSession(['package_booking' => $this->stagedBooking($package)])
            ->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery']);
        $this->actingAs($second)->withSession(['package_booking' => $this->stagedBooking($package)])
            ->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery'])
            ->assertSessionHas('error');

        $this->assertSame(1, Order::bookings()->whereDate('event_date', '2026-11-20')->count());
        $this->assertSame(1, Order::where('user_id', $first->id)->count());
    }

    private function stagedBooking(CateringPackage $package): array
    {
        return [
            'package_id' => $package->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'landmark' => null, 'venue' => '123 Test St, Caminawit, San Jose',
            'event_date' => '2026-11-20 15:00', 'event_type' => null,
        ];
    }

    public function test_reserve_catering_takes_a_row_lock_inside_the_transaction(): void
    {
        $lockQueries = [];
        DB::listen(function ($query) use (&$lockQueries) {
            if (str_contains($query->sql, 'booking_locks') && str_starts_with(strtolower($query->sql), 'select')) {
                $lockQueries[] = ['bindings' => $query->bindings, 'level' => DB::transactionLevel()];
            }
        });

        $customerId = $this->customer()->id;

        // First booking for this date: the lock row is created, then locked.
        DB::transaction(fn () => BookingCapacity::reserveCatering(Carbon::parse('2026-11-20 15:00'), $customerId));

        $this->assertNotEmpty($lockQueries);
        foreach ($lockQueries as $query) {
            $this->assertSame(['catering:2026-11-20'], $query['bindings']);
            $this->assertGreaterThanOrEqual(1, $query['level'], 'The lock must be taken inside a transaction.');
        }
        $this->assertTrue(DB::table('booking_locks')->where('key', 'catering:2026-11-20')->exists());

        // Row already exists: a single SELECT ... FOR UPDATE, no INSERT
        // first (INSERT IGNORE on an existing row is what deadlocked).
        $lockQueries = [];
        DB::transaction(fn () => BookingCapacity::reserveCatering(Carbon::parse('2026-11-20 18:00'), $customerId + 999));
        $this->assertCount(1, $lockQueries);
    }

    public function test_lock_query_uses_for_update_on_mysql(): void
    {
        // SQLite (the test DB) ignores FOR UPDATE, so assert the grammar
        // MySQL — production — compiles for the exact same builder call.
        $mysql = new \Illuminate\Database\Query\Grammars\MySqlGrammar(DB::connection());
        $builder = DB::table('booking_locks')->where('key', 'x')->lockForUpdate();

        $this->assertStringEndsWith('for update', $mysql->compileSelect($builder));
    }

    public function test_blocked_date_cannot_be_booked_for_catering(): void
    {
        BlockedDate::create(['date' => '2026-11-20', 'reason' => 'Town fiesta']);

        $this->actingAs($this->customer())
            ->post('/customer/packages/avail', $this->availPayload($this->package(), '2026-11-20 15:00'))
            ->assertSessionHasErrors(['event_date' => 'Nov 20, 2026 is not available for bookings (Town fiesta). Please choose another date.']);
    }

    public function test_catering_lead_time_and_max_advance_are_enforced(): void
    {
        $package = $this->package();
        $customer = $this->customer();

        // Today is Nov 4; 3-day lead → Nov 7 is the earliest date.
        $this->actingAs($customer)->post('/customer/packages/avail', $this->availPayload($package, '2026-11-06 15:00'))
            ->assertSessionHasErrors('event_date');
        $this->actingAs($customer)->post('/customer/packages/avail', $this->availPayload($package, '2026-11-07 09:00'))
            ->assertSessionHasNoErrors();

        // 6 months ahead → May 4, 2027 is the latest date.
        $this->actingAs($customer)->post('/customer/packages/avail', $this->availPayload($package, '2027-05-05 09:00'))
            ->assertSessionHasErrors('event_date');
        $this->actingAs($customer)->post('/customer/packages/avail', $this->availPayload($package, '2027-05-04 09:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_split_day_and_time_fields_are_combined(): void
    {
        $payload = $this->availPayload($this->package(), '');
        unset($payload['event_date']);

        $this->actingAs($this->customer())
            ->post('/customer/packages/avail', $payload + ['event_day' => '2026-11-20', 'event_time' => '17:30'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-20 17:30', session('package_booking.event_date'));
    }

    public function test_same_customer_cannot_book_two_catering_events_on_one_date(): void
    {
        $customer = $this->customer();
        $this->existingBookingOn('2026-11-20', ['user_id' => $customer->id]);

        $this->actingAs($customer)
            ->post('/customer/packages/avail', $this->availPayload($this->package(), '2026-11-20 18:00'))
            ->assertSessionHasErrors('event_date');

        // A different customer can still take that date.
        $this->actingAs($this->customer())
            ->post('/customer/packages/avail', $this->availPayload($this->package(), '2026-11-20 18:00'))
            ->assertSessionHasNoErrors();
    }

    public function test_packages_page_exposes_full_and_blocked_dates_to_the_calendar(): void
    {
        foreach (range(1, 3) as $i) {
            $this->existingBookingOn('2026-11-20');
        }
        BlockedDate::create(['date' => '2026-11-25', 'reason' => 'Holiday']);

        $response = $this->actingAs($this->customer())->get('/customer/packages');

        $response->assertOk();
        $calendar = $response->viewData('eventCalendar');
        $this->assertSame('2026-11-07', $calendar['minDate']);
        $this->assertSame('2027-05-04', $calendar['maxDate']);
        $this->assertSame(['2026-11-20' => 'Fully booked', '2026-11-25' => 'Closed — Holiday'], $calendar['unavailable']);
    }

    // ---- menu orders: time-frame capacity, blocked dates -------------------

    public function test_full_time_frame_is_flagged_and_rejected(): void
    {
        BookingSettings::save(['max_orders_per_time_frame' => 2]);
        $slot = $this->firstSlot();
        $this->scheduledOrderIn($slot);
        $this->scheduledOrderIn($slot);
        $customer = $this->customer();
        $this->withMenuCart();

        $slots = $this->actingAs($customer)
            ->getJson('/customer/checkout/delivery-slots?date=' . $this->now->toDateString())
            ->assertOk()
            ->json('slots');
        $this->assertTrue($slots[0]['full']);
        $this->assertFalse($slots[1]['full']);

        $this->actingAs($customer)->post('/customer/orders', $this->menuOrderPayload([
            'delivery_type' => 'scheduled', 'delivery_date' => $this->now->toDateString(), 'delivery_slot' => $slot['value'],
        ]))->assertSessionHasErrors('delivery_slot');

        $this->assertSame(0, Order::where('user_id', $customer->id)->count());
    }

    public function test_asap_and_cancelled_orders_do_not_count_toward_a_time_frame(): void
    {
        BookingSettings::save(['max_orders_per_time_frame' => 1]);
        $slot = $this->firstSlot();
        $this->scheduledOrderIn($slot, ['status' => 'cancelled']);
        $this->existingOrder(['delivery_type' => 'asap']);
        $customer = $this->customer();
        $this->withMenuCart();

        $this->actingAs($customer)->post('/customer/orders', $this->menuOrderPayload([
            'delivery_type' => 'scheduled', 'delivery_date' => $this->now->toDateString(), 'delivery_slot' => $slot['value'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
    }

    public function test_blocked_date_rejects_scheduled_and_asap_menu_orders(): void
    {
        BlockedDate::create(['date' => $this->now->toDateString(), 'reason' => 'Staff outing']);
        $customer = $this->customer();
        $slot = $this->firstSlot();

        $this->withMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuOrderPayload())
            ->assertSessionHasErrors('delivery_type');

        $this->withMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuOrderPayload([
            'delivery_type' => 'scheduled', 'delivery_date' => $this->now->toDateString(), 'delivery_slot' => $slot['value'],
        ]))->assertSessionHasErrors('delivery_slot');

        $this->assertSame(0, Order::count());

        $this->actingAs($customer)
            ->getJson('/customer/checkout/delivery-slots?date=' . $this->now->toDateString())
            ->assertJson(['slots' => [], 'blocked' => 'Staff outing']);
    }

    public function test_checkout_disables_asap_when_today_is_blocked(): void
    {
        BlockedDate::create(['date' => $this->now->toDateString(), 'reason' => 'Staff outing']);
        $this->withMenuCart();

        $this->actingAs($this->customer())->get('/customer/checkout')
            ->assertOk()
            ->assertSee('Unavailable today')
            ->assertSee("not taking same-day orders today (Staff outing)", false);
    }

    public function test_second_order_in_same_time_frame_needs_confirmation(): void
    {
        $customer = $this->customer();
        $slot = $this->firstSlot();
        $this->scheduledOrderIn($slot, ['user_id' => $customer->id]);
        $payload = $this->menuOrderPayload([
            'delivery_type' => 'scheduled', 'delivery_date' => $this->now->toDateString(), 'delivery_slot' => $slot['value'],
        ]);

        $this->withMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $payload)
            ->assertSessionHasErrors('delivery_slot')
            ->assertSessionHas('needs_duplicate_slot_confirmation', true);
        $this->assertSame(1, Order::where('user_id', $customer->id)->count());

        $this->withMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $payload + ['confirm_duplicate_slot' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Order::where('user_id', $customer->id)->count());
    }

    // ---- cart package path ------------------------------------------------

    public function test_cart_add_package_route_is_disabled(): void
    {
        $package = $this->package();

        $this->actingAs($this->customer())
            ->post('/customer/cart/add-package', ['package_id' => $package->id])
            ->assertRedirect(route('customer.packages.index'));

        $this->assertEmpty(session('cart', []));
    }
}
