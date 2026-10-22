<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Models\Utensil;
use App\Services\OrderCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage 3: admins block/unblock customers and flag orders as No-show/Fake;
// utensil stock comes back exactly once on ANY cancellation.
class BlockingAndFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
    }

    // ---- helpers ----------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer', 'email_verified_at' => now()], $attributes));
    }

    private function blockedCustomer(): User
    {
        return $this->customer(['blocked_at' => now(), 'blocked_reason' => 'Fake bookings']);
    }

    /** A package that needs 10 chairs + 2 chafing dishes, with 50 / 10 in stock. */
    private function packageWithUtensils(): array
    {
        $chairs = Utensil::create(['name' => 'Chairs', 'quantity' => 50]);
        $chafing = Utensil::create(['name' => 'Chafing Dish', 'quantity' => 10]);
        $package = CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => 2000, 'is_available' => true]);
        $package->utensils()->attach([$chairs->id => ['quantity_needed' => 10], $chafing->id => ['quantity_needed' => 2]]);

        return [$package, $chairs, $chafing];
    }

    /** Books the package through the real Avail flow; returns the order. */
    private function bookPackage(User $customer, CateringPackage $package): Order
    {
        $this->actingAs($customer)->post('/customer/packages/avail', [
            'package_id' => $package->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'event_date' => '2026-11-20 15:00',
        ])->assertSessionHasNoErrors();
        $this->actingAs($customer)->post('/customer/packages/checkout', ['payment_method' => 'cash_on_delivery']);

        return Order::bookings()->where('user_id', $customer->id)->latest('id')->firstOrFail();
    }

    private function fillMenuCart(): void
    {
        $item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains'])->id,
            'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true,
        ]);
        session(['cart' => ['menu_' . $item->id => ['id' => $item->id, 'name' => 'Adobo', 'price' => 150, 'quantity' => 1, 'type' => 'menu']]]);
    }

    // ---- blocking ----------------------------------------------------------

    public function test_admin_can_block_and_unblock_a_customer_with_logging(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)->post("/admin/users/{$customer->id}/block", ['reason' => 'Three fake bookings'])
            ->assertRedirect(route('admin.users'));

        $customer->refresh();
        $this->assertTrue($customer->isBlocked());
        $this->assertSame('Three fake bookings', $customer->blocked_reason);
        $this->assertSame($admin->id, $customer->blocked_by);
        $this->assertTrue(ActivityLog::where('action', 'user.blocked')->where('subject_id', $customer->id)->exists());
        $this->assertFalse(ActivityLog::where('action', 'user.updated')->exists(), 'No duplicate generic entry.');

        $this->actingAs($admin)->post("/admin/users/{$customer->id}/unblock")->assertRedirect(route('admin.users'));

        $customer->refresh();
        $this->assertFalse($customer->isBlocked());
        $this->assertNull($customer->blocked_reason);
        $this->assertTrue(ActivityLog::where('action', 'user.unblocked')->exists());
    }

    public function test_block_requires_a_reason_and_only_applies_to_customers(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $rider = User::factory()->create(['role' => 'delivery']);

        $this->actingAs($admin)->post("/admin/users/{$customer->id}/block", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/users/{$rider->id}/block", ['reason' => 'x'])->assertSessionHas('error');
        $this->actingAs($admin)->post("/admin/users/{$admin->id}/block", ['reason' => 'x'])->assertSessionHas('error');

        $this->assertFalse($customer->fresh()->isBlocked());
        $this->assertFalse($rider->fresh()->isBlocked());
        $this->assertFalse($admin->fresh()->isBlocked());
    }

    public function test_customers_cannot_block_anyone(): void
    {
        $customer = $this->customer();
        $other = $this->customer();

        $this->actingAs($customer)->post("/admin/users/{$other->id}/block", ['reason' => 'x']);
        $this->assertFalse($other->fresh()->isBlocked());
    }

    public function test_blocked_customer_cannot_reach_any_ordering_route(): void
    {
        $blocked = $this->blockedCustomer();
        [$package] = $this->packageWithUtensils();
        $restricted = route('customer.account-restricted');

        $this->fillMenuCart();
        $this->actingAs($blocked)->get('/customer/checkout')->assertRedirect($restricted);
        $this->actingAs($blocked)->get('/customer/checkout/delivery-slots?date=2026-11-04')->assertRedirect($restricted);
        $this->actingAs($blocked)->post('/customer/orders', [])->assertRedirect($restricted);
        $this->actingAs($blocked)->post('/customer/packages/avail', ['package_id' => $package->id])->assertRedirect($restricted);
        $this->actingAs($blocked)->get('/customer/packages/checkout')->assertRedirect($restricted);
        $this->actingAs($blocked)->post('/customer/packages/checkout', [])->assertRedirect($restricted);

        $this->assertSame(0, Order::count());
    }

    public function test_blocked_customer_keeps_orders_chat_and_sees_a_polite_notice(): void
    {
        $this->admin(); // chat needs an admin account to talk to
        $blocked = $this->blockedCustomer();
        $this->packageWithUtensils();

        $this->actingAs($blocked)->get('/customer/orders')->assertOk();
        $this->actingAs($blocked)->get('/customer/chat/messages')->assertOk();
        $this->actingAs($blocked)->get('/customer/dashboard')->assertOk();

        $this->actingAs($blocked)->get('/customer/account-restricted')
            ->assertOk()
            ->assertSee('New orders and bookings are paused on your account.')
            ->assertSee('Message us')
            ->assertDontSee('Fake bookings'); // the admin's reason is never shown

        $this->actingAs($blocked)->get('/customer/packages')
            ->assertSee('New orders and bookings are paused')
            ->assertSee('Booking unavailable')
            ->assertDontSee('Avail This Package');

        $this->fillMenuCart();
        $this->actingAs($blocked)->get('/customer/cart')
            ->assertSee('Checkout unavailable')
            ->assertDontSee('Proceed to Checkout');
    }

    public function test_unblocked_customer_can_order_again(): void
    {
        $customer = $this->blockedCustomer();
        $this->actingAs($this->admin())->post("/admin/users/{$customer->id}/unblock");

        $this->fillMenuCart();
        $this->actingAs($customer->fresh())->get('/customer/checkout')->assertOk();
    }

    // ---- No-show / Fake ------------------------------------------------------

    public function test_admin_can_mark_a_booking_as_no_show(): void
    {
        [$package, $chairs] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);
        $this->assertSame(40, $chairs->fresh()->quantity);

        $this->actingAs($this->admin())
            ->post("/admin/orders/{$booking->id}/flag", ['flag' => 'no_show', 'reason' => 'Nobody at the venue'])
            ->assertRedirect(route('admin.orders.show', $booking->id));

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame('no_show', $booking->flag);
        $this->assertSame('No-show', $booking->flagLabel());
        $this->assertSame('Nobody at the venue', $booking->cancellation_reason);
        $this->assertNotNull($booking->flagged_at);
        $this->assertSame(50, $chairs->fresh()->quantity, 'Utensils come back on a No-show.');
        $this->assertTrue(ActivityLog::where('action', 'order.flagged_no_show')->exists());
        $this->assertTrue(ActivityLog::where('action', 'stock.utensils_restored')->exists());
    }

    public function test_mark_fake_can_also_block_the_customer(): void
    {
        [$package] = $this->packageWithUtensils();
        $customer = $this->customer();
        $booking = $this->bookPackage($customer, $package);

        $this->actingAs($this->admin())->post("/admin/orders/{$booking->id}/flag", [
            'flag' => 'fake', 'reason' => 'Prank', 'block_customer' => '1',
        ]);

        $this->assertSame('fake', $booking->fresh()->flag);
        $customer->refresh();
        $this->assertTrue($customer->isBlocked());
        $this->assertStringContainsString("Fake on #{$booking->order_number}", $customer->blocked_reason);
    }

    public function test_flag_shows_to_admins_but_customers_only_see_cancelled(): void
    {
        [$package] = $this->packageWithUtensils();
        $customer = $this->customer();
        $booking = $this->bookPackage($customer, $package);
        $admin = $this->admin();
        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/flag", ['flag' => 'fake', 'reason' => 'Prank call']);

        $this->actingAs($admin)->get('/admin/bookings')->assertSee('badge-flag', false)->assertSee('Fake');
        $this->actingAs($admin)->get("/admin/orders/{$booking->id}")->assertSee('Prank call');

        $this->actingAs($customer)->get("/customer/orders/{$booking->id}")
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertDontSee('badge-flag', false)
            ->assertDontSee('Prank call');
    }

    public function test_only_active_orders_can_be_flagged(): void
    {
        [$package] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);
        $booking->update(['status' => 'delivered']);

        $this->actingAs($this->admin())->post("/admin/orders/{$booking->id}/flag", ['flag' => 'no_show'])
            ->assertSessionHas('error');
        $this->assertNull($booking->fresh()->flag);

        $this->actingAs($this->admin())->post("/admin/orders/{$booking->id}/flag", ['flag' => 'bogus'])
            ->assertSessionHasErrors('flag');
    }

    // ---- stock restore on ANY cancellation ------------------------------------

    public function test_admin_cancel_restores_utensils_exactly_once(): void
    {
        [$package, $chairs, $chafing] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);
        $this->assertSame([$chairs->id => 10, $chafing->id => 2], $booking->reserved_utensils);
        $this->assertSame(40, $chairs->fresh()->quantity);
        $this->assertSame(8, $chafing->fresh()->quantity);

        $admin = $this->admin();
        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/status", ['status' => 'cancelled']);

        $this->assertSame(50, $chairs->fresh()->quantity);
        $this->assertSame(10, $chafing->fresh()->quantity);
        $this->assertNotNull($booking->fresh()->stock_restored_at);

        // Cancel again, call the service directly, flag it — still only once.
        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/status", ['status' => 'cancelled']);
        OrderCancellation::restoreStock($booking->fresh());
        $booking->fresh()->update(['status' => 'cancelled']);

        $this->assertSame(50, $chairs->fresh()->quantity);
        $this->assertSame(10, $chafing->fresh()->quantity);
        $this->assertSame(1, ActivityLog::where('action', 'stock.utensils_restored')->count());
    }

    public function test_restore_returns_what_was_reserved_even_if_the_package_changed(): void
    {
        [$package, $chairs] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);

        // Admin edits the package afterwards to need 25 chairs.
        $package->utensils()->updateExistingPivot($chairs->id, ['quantity_needed' => 25]);

        OrderCancellation::flag($booking, 'no_show', null);

        $this->assertSame(50, $chairs->fresh()->quantity, 'Exactly the 10 reserved chairs come back, not 25.');
    }

    public function test_old_orders_without_a_reservation_record_fall_back_to_package_contents(): void
    {
        [$package, $chairs] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);
        $booking->forceFill(['reserved_utensils' => null])->saveQuietly(); // as if placed before this stage

        $booking->update(['status' => 'cancelled']);

        $this->assertSame(50, $chairs->fresh()->quantity);
    }

    public function test_menu_only_order_cancel_touches_no_stock(): void
    {
        [, $chairs] = $this->packageWithUtensils();
        $order = Order::create([
            'user_id' => $this->customer()->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567',
        ]);

        $order->update(['status' => 'cancelled']);

        $this->assertSame(50, $chairs->fresh()->quantity);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertFalse(ActivityLog::where('action', 'stock.utensils_restored')->exists());
    }

    public function test_reopening_a_cancelled_order_reserves_its_utensils_again(): void
    {
        [$package, $chairs] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/flag", ['flag' => 'fake']);
        $this->assertSame(50, $chairs->fresh()->quantity);

        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/status", ['status' => 'confirmed']);

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertNull($booking->flag);
        $this->assertNull($booking->stock_restored_at);
        $this->assertSame(40, $chairs->fresh()->quantity);

        // ...and cancelling it again returns them again (the guard was reset).
        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/status", ['status' => 'cancelled']);
        $this->assertSame(50, $chairs->fresh()->quantity);
    }

    public function test_reopen_is_refused_when_the_utensils_are_gone(): void
    {
        [$package, $chairs] = $this->packageWithUtensils();
        $booking = $this->bookPackage($this->customer(), $package);
        $admin = $this->admin();
        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/status", ['status' => 'cancelled']);

        $chairs->update(['quantity' => 3]); // lent out elsewhere meanwhile

        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertSessionHas('error');

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(3, $chairs->fresh()->quantity);
    }
}
