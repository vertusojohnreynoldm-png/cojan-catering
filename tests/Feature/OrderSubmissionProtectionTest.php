<?php

namespace Tests\Feature;

use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Services\BookingSettings;
use App\Services\BusinessHours;
use App\Services\OrderSubmissionGuard;
use App\Services\SubmissionToken;
use Database\Seeders\AdminSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage 2 of the fake/double-booking work: one-time submission tokens,
// the rate limit, per-customer pending limits, the verified-email gate,
// the local-only business-hours bypass, and the fixed demo-account seeder.
class OrderSubmissionProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 10:00 AM Manila — inside business hours.
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
    }

    // ---- helpers ----------------------------------------------------------

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer', 'email_verified_at' => now()], $attributes));
    }

    private function fillMenuCart(): void
    {
        $item = MenuItem::first() ?? MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains'])->id,
            'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true,
        ]);

        session(['cart' => [
            'menu_' . $item->id => ['id' => $item->id, 'name' => $item->name, 'price' => 150, 'quantity' => 1, 'type' => 'menu'],
        ]]);
    }

    private function menuPayload(array $overrides = []): array
    {
        return array_merge([
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'delivery_phone' => '09171234567',
            'payment_method' => 'cash_on_delivery', 'delivery_type' => 'asap',
        ], $overrides);
    }

    private function package(): CateringPackage
    {
        return CateringPackage::first()
            ?? CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => 2000, 'is_available' => true]);
    }

    private function stageBooking(string $eventDate = '2026-11-20 15:00'): void
    {
        session(['package_booking' => [
            'package_id' => $this->package()->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'landmark' => null, 'venue' => '123 Test St, Caminawit, San Jose',
            'event_date' => $eventDate, 'event_type' => null,
        ]]);
    }

    private function availPayload(string $eventDate = '2026-11-20 15:00'): array
    {
        return [
            'package_id' => $this->package()->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'event_date' => $eventDate,
        ];
    }

    private function existingOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id, 'order_number' => 'ORD-' . strtoupper(Str::random(8)),
            'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 100, 'delivery_fee' => 0, 'total_amount' => 100,
            'delivery_address' => 'Somewhere', 'delivery_phone' => '09171234567',
        ], $attributes));
    }

    // ---- one-time submission token ---------------------------------------

    public function test_double_submit_of_menu_checkout_creates_one_order(): void
    {
        $customer = $this->customer();
        $this->fillMenuCart();
        $token = SubmissionToken::issue('checkout');

        $first = $this->actingAs($customer)->post('/customer/orders', $this->menuPayload(['submission_token' => $token]));
        $order = Order::sole();
        $first->assertRedirect(route('customer.orders.show', $order->id));

        // Same token again (double tap / browser retry) — the cart is even
        // refilled to prove it isn't just "cart empty" stopping it.
        $this->fillMenuCart();
        $second = $this->actingAs($customer)->post('/customer/orders', $this->menuPayload(['submission_token' => $token]));

        $second->assertRedirect(route('customer.orders.show', $order->id));
        $second->assertSessionHas('success', 'Your order was already placed — here it is.');
        $this->assertSame(1, Order::count());
        $this->assertSame($token, $order->idempotency_key);
    }

    public function test_double_submit_of_package_checkout_creates_one_booking(): void
    {
        $customer = $this->customer();
        $this->stageBooking();
        $token = SubmissionToken::issue('package_checkout');

        $this->actingAs($customer)->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery', 'submission_token' => $token]);
        $this->stageBooking();
        $second = $this->actingAs($customer)->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery', 'submission_token' => $token]);

        $booking = Order::bookings()->sole();
        $second->assertRedirect(route('customer.orders.show', $booking->id));
        $second->assertSessionHas('success', 'Your booking was already placed — here it is.');
    }

    public function test_missing_or_unknown_token_creates_nothing(): void
    {
        $customer = $this->customer();
        $this->fillMenuCart();

        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload(['submission_token' => '']))
            ->assertRedirect(route('customer.checkout'))
            ->assertSessionHas('error');
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload(['submission_token' => (string) Str::uuid()]))
            ->assertRedirect(route('customer.checkout'));

        $this->assertSame(0, Order::count());
    }

    public function test_another_customers_token_does_not_reveal_their_order(): void
    {
        $owner = $this->customer();
        $this->existingOrder($owner, ['idempotency_key' => 'shared-token']);

        $this->fillMenuCart();
        $this->actingAs($this->customer())->post('/customer/orders', $this->menuPayload(['submission_token' => 'shared-token']))
            ->assertRedirect(route('customer.checkout'));
    }

    public function test_database_rejects_two_orders_with_the_same_token(): void
    {
        // The guarantee for two truly simultaneous requests that both get
        // past the session check: the second INSERT is refused.
        $customer = $this->customer();
        $this->existingOrder($customer, ['idempotency_key' => 'race-token']);

        try {
            $this->existingOrder($customer, ['idempotency_key' => 'race-token']);
            $this->fail('A second order with the same idempotency key was inserted.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('idempotency_key', $e->getMessage());
        }

        $this->assertSame(1, Order::count());
    }

    public function test_checkout_forms_render_a_token_and_submit_once_markup(): void
    {
        $customer = $this->customer();
        $this->fillMenuCart();

        $this->actingAs($customer)->get('/customer/checkout')
            ->assertOk()
            ->assertSee('name="submission_token"', false)
            ->assertSee('data-submit-once', false)
            ->assertSee('data-loading-text="Placing order…"', false);

        $this->stageBooking();
        $this->actingAs($customer)->get('/customer/packages/checkout')
            ->assertOk()
            ->assertSee('name="submission_token"', false)
            ->assertSee('data-loading-text="Confirming booking…"', false);

        $this->actingAs($customer)->get('/customer/packages')->assertSee('id="avail-form" data-submit-once', false);
    }

    // ---- verified email --------------------------------------------------

    public function test_unverified_customer_is_sent_to_the_verify_page(): void
    {
        $unverified = $this->customer(['email_verified_at' => null]);
        $this->fillMenuCart();

        $this->actingAs($unverified)->get('/customer/checkout')->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->post('/customer/orders', $this->menuPayload())->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->post('/customer/packages/avail', $this->availPayload())->assertRedirect(route('verification.notice'));
        $this->stageBooking();
        $this->actingAs($unverified)->get('/customer/packages/checkout')->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery'])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, Order::count());

        $this->actingAs($unverified)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Resend Verification Email');
    }

    // ---- pending limits --------------------------------------------------

    public function test_fourth_pending_menu_order_is_refused(): void
    {
        $customer = $this->customer();
        foreach (range(1, 3) as $i) {
            $this->existingOrder($customer);
        }

        $this->fillMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload())
            ->assertSessionHasErrors('pending_limit');
        $this->assertSame(3, Order::count());

        $this->fillMenuCart();
        $this->actingAs($customer)->get('/customer/checkout')
            ->assertSee('You already have 3 orders waiting for confirmation')
            ->assertSee('Order limit reached');
    }

    public function test_confirmed_and_booking_orders_do_not_count_toward_the_menu_limit(): void
    {
        $customer = $this->customer();
        $this->existingOrder($customer);
        $this->existingOrder($customer);
        $this->existingOrder($customer, ['status' => 'confirmed']);
        $this->existingOrder($customer, ['event_date' => '2026-11-20 15:00:00']);

        $this->fillMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload())->assertSessionHasNoErrors();

        $this->assertSame(3, Order::regularOrders()->where('status', 'pending')->count());
    }

    public function test_third_pending_catering_booking_is_refused_at_every_step(): void
    {
        $customer = $this->customer();
        $this->package();
        $this->existingOrder($customer, ['event_date' => '2026-11-18 15:00:00']);
        $this->existingOrder($customer, ['event_date' => '2026-11-19 15:00:00']);

        $this->actingAs($customer)->get('/customer/packages')
            ->assertSee('You already have 2 catering bookings waiting for confirmation')
            ->assertSee('Booking limit reached');

        $this->actingAs($customer)->post('/customer/packages/avail', $this->availPayload())
            ->assertRedirect(route('customer.packages.index'))
            ->assertSessionHas('error');

        // Even with step 1 bypassed, the final confirmation refuses it.
        $this->stageBooking();
        $this->actingAs($customer)->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery'])
            ->assertSessionHasErrors('pending_limit');

        $this->assertSame(2, Order::bookings()->count());
    }

    public function test_pending_limit_follows_the_admin_setting(): void
    {
        BookingSettings::save(['max_pending_menu_orders' => 1]);
        $customer = $this->customer();
        $this->existingOrder($customer);

        $this->fillMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload())->assertSessionHasErrors('pending_limit');
    }

    // ---- rate limit ------------------------------------------------------

    public function test_sixth_submission_in_ten_minutes_is_blocked(): void
    {
        $customer = $this->customer();
        $invalid = $this->menuPayload(['delivery_phone' => 'not-a-phone']);

        foreach (range(1, 5) as $i) {
            $this->fillMenuCart();
            $this->actingAs($customer)->post('/customer/orders', $invalid)->assertSessionHasErrors('delivery_phone');
        }

        $this->fillMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload())
            ->assertSessionHasErrors(['rate_limit' => 'Too many order attempts in a short time. Please wait 10 minutes and try again.']);
        $this->assertSame(0, Order::count());

        // Another customer isn't affected...
        $this->fillMenuCart();
        $this->actingAs($this->customer())->post('/customer/orders', $this->menuPayload())->assertSessionHasNoErrors();

        // ...and the first customer can order again once the window passes.
        $this->travel(11)->minutes();
        $this->fillMenuCart();
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload())->assertSessionHasNoErrors();
    }

    public function test_rate_limit_is_shared_with_catering_and_replays_do_not_count(): void
    {
        $customer = $this->customer();
        foreach (range(1, 5) as $i) {
            RateLimiter::hit(OrderSubmissionGuard::rateLimitKey($customer->id), OrderSubmissionGuard::DECAY_SECONDS);
        }

        $this->stageBooking();
        $this->actingAs($customer)->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery'])
            ->assertSessionHasErrors('rate_limit');

        // A replayed token for an order that already exists is answered
        // even while rate-limited (it creates nothing).
        $order = $this->existingOrder($customer, ['idempotency_key' => 'already-done']);
        $this->actingAs($customer)->post('/customer/orders', $this->menuPayload(['submission_token' => 'already-done']))
            ->assertRedirect(route('customer.orders.show', $order->id));
    }

    // ---- local-only business-hours bypass ---------------------------------

    public function test_business_hours_bypass_is_ignored_outside_local(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 23:30:00', 'Asia/Manila'));
        config(['app.business_hours_bypass' => true]);

        // The test suite runs as APP_ENV=testing — same as production here.
        $this->assertFalse(BusinessHours::bypassActive());
        $this->assertFalse(BusinessHours::isMenuOrderingOpen());

        $this->app['env'] = 'production';
        $this->assertFalse(BusinessHours::isMenuOrderingOpen());
    }

    public function test_business_hours_bypass_opens_ordering_when_local(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 23:30:00', 'Asia/Manila'));
        config(['app.business_hours_bypass' => true]);
        $this->app['env'] = 'local';

        $this->assertTrue(BusinessHours::isMenuOrderingOpen());
        $this->assertNull(BusinessHours::minutesUntilClose());

        $this->fillMenuCart();
        $this->actingAs($this->customer())->get('/customer/checkout')
            ->assertOk()
            ->assertSee('Local testing: business hours are bypassed');

        config(['app.business_hours_bypass' => false]);
        $this->assertFalse(BusinessHours::isMenuOrderingOpen());
    }

    // ---- seeder ------------------------------------------------------------

    public function test_seeded_accounts_have_valid_phones_and_can_order(): void
    {
        $this->seed(AdminSeeder::class);

        foreach (['admin@cojan.com', 'customer@cojan.com', 'delivery@cojan.com'] as $email) {
            $user = User::where('email', $email)->sole();
            $this->assertTrue(
                Validator::make(['p' => $user->phone], ['p' => [new PhilippineMobileNumber]])->passes(),
                "$email has an invalid phone: {$user->phone}"
            );
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function test_reseeding_fixes_old_placeholder_phone_without_touching_password(): void
    {
        $old = User::factory()->create([
            'email' => 'customer@cojan.com', 'role' => 'customer', 'phone' => '09111111111',
            'password' => Hash::make('changed-by-admin'),
        ]);

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class); // re-runnable

        $fresh = $old->fresh();
        $this->assertSame('09171234502', $fresh->phone);
        $this->assertTrue(Hash::check('changed-by-admin', $fresh->password));
        $this->assertSame(1, User::where('email', 'customer@cojan.com')->count());
    }
}
