<?php

namespace Tests\Feature;

use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// "Max quantity per menu item per order" (Booking Rules, default 20): enforced
// on add to cart, cart update and checkout. Prices always come from the
// database. Catering packages are not capped.
class MenuQuantityLimitTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $adobo;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));

        $this->adobo = MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains'])->id,
            'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true,
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function key(): string
    {
        return 'menu_' . $this->adobo->id;
    }

    private function cartWith(int $qty, float $price = 150): void
    {
        session(['cart' => [$this->key() => [
            'type' => 'menu', 'id' => $this->adobo->id, 'name' => 'Adobo',
            'price' => $price, 'quantity' => $qty, 'image' => null,
        ]]]);
    }

    private function cartQty(): ?int
    {
        return session('cart')[$this->key()]['quantity'] ?? null;
    }

    private function orderPayload(): array
    {
        return [
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'delivery_phone' => '09171234567',
            'payment_method' => 'cash_on_delivery', 'delivery_type' => 'asap',
        ];
    }

    // ---- add to cart -----------------------------------------------------------

    public function test_add_within_the_limit(): void
    {
        $this->actingAs($this->customer())
            ->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 12])
            ->assertRedirect(route('customer.cart'))
            ->assertSessionHas('success');

        $this->assertSame(12, $this->cartQty());
    }

    public function test_adding_past_the_limit_caps_at_the_limit_with_a_message(): void
    {
        $this->actingAs($this->customer());
        $this->cartWith(15);

        $this->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 10])
            ->assertRedirect(route('customer.cart'))
            ->assertSessionHas('error', 'Maximum 20 per item per order. You now have 20 × Adobo in your cart.');

        $this->assertSame(20, $this->cartQty());
    }

    public function test_a_single_tampered_add_over_the_limit_is_rejected(): void
    {
        $this->actingAs($this->customer())
            ->from('/customer/menu')
            ->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 21])
            ->assertRedirect('/customer/menu')
            ->assertSessionHas('error', 'Maximum 20 per item per order.');

        $this->assertNull(session('cart'));
    }

    public function test_invalid_quantities_are_rejected_on_add(): void
    {
        $this->actingAs($this->customer());

        foreach (['abc', '0', '-3', '2.5'] as $bad) {
            $this->from('/customer/menu')
                ->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => $bad])
                ->assertSessionHas('error');
        }

        $this->assertEmpty(session('cart', []));
    }

    public function test_add_stores_the_database_price_not_a_posted_one(): void
    {
        $this->actingAs($this->customer())
            ->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 2, 'price' => 1]);

        $this->assertEquals(150, session('cart')[$this->key()]['price']);
    }

    // ---- cart update -----------------------------------------------------------

    public function test_update_within_the_limit_and_zero_removes(): void
    {
        $this->actingAs($this->customer());
        $this->cartWith(3);

        $this->post('/customer/cart/update/' . $this->key(), ['quantity' => 20])->assertSessionHas('success');
        $this->assertSame(20, $this->cartQty());

        $this->post('/customer/cart/update/' . $this->key(), ['quantity' => 0]);
        $this->assertArrayNotHasKey($this->key(), session('cart'));
    }

    public function test_update_over_the_limit_or_invalid_is_rejected_and_cart_unchanged(): void
    {
        $this->actingAs($this->customer());
        $this->cartWith(3);

        $this->post('/customer/cart/update/' . $this->key(), ['quantity' => 21])
            ->assertRedirect(route('customer.cart'))
            ->assertSessionHas('error', 'Maximum 20 per item per order.');
        $this->assertSame(3, $this->cartQty());

        foreach (['', 'ten', '-1', '4.5'] as $bad) {
            $this->post('/customer/cart/update/' . $this->key(), ['quantity' => $bad])->assertSessionHas('error');
            $this->assertSame(3, $this->cartQty());
        }
    }

    public function test_packages_are_not_capped(): void
    {
        $package = CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => 2000, 'is_available' => true]);
        $this->actingAs($this->customer());
        session(['cart' => ['package_' . $package->id => [
            'type' => 'package', 'id' => $package->id, 'name' => 'Fiesta', 'price' => 2000, 'quantity' => 1, 'pax' => 20,
        ]]]);

        $this->post('/customer/cart/update/package_' . $package->id, ['quantity' => 25])->assertSessionHas('success');
        $this->assertSame(25, session('cart')['package_' . $package->id]['quantity']);

        $this->get('/customer/cart')->assertOk()->assertDontSee('maximum per item per order');
        $this->assertSame(25, session('cart')['package_' . $package->id]['quantity']);
    }

    // ---- existing / tampered carts at checkout ----------------------------------

    public function test_an_over_limit_cart_is_capped_on_the_cart_and_checkout_pages(): void
    {
        $this->actingAs($this->customer());
        $this->cartWith(35);

        $this->get('/customer/checkout')
            ->assertOk()
            ->assertSee('We set Adobo to 20 — the maximum per item per order.')
            ->assertSee('3,000.00'); // 20 × ₱150

        $this->assertSame(20, $this->cartQty());
    }

    public function test_a_tampered_cart_is_capped_and_not_ordered_at_submit(): void
    {
        $this->actingAs($this->customer());
        $this->cartWith(500);

        $this->post('/customer/orders', $this->orderPayload())
            ->assertRedirect(route('customer.checkout'))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'We set Adobo to 20'));

        $this->assertSame(0, Order::count());
        $this->assertSame(20, $this->cartQty());

        // Reviewed and re-submitted: the order goes through at the capped quantity.
        $this->post('/customer/orders', $this->orderPayload())->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertSame(20, (int) $order->orderItems()->sole()->quantity);
        $this->assertEquals(3000, $order->subtotal);
    }

    public function test_order_total_uses_the_database_price_not_the_session_price(): void
    {
        $this->actingAs($this->customer());
        $this->cartWith(2, price: 1.00); // stale or tampered price in the session

        $this->post('/customer/orders', $this->orderPayload())->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertEquals(300, $order->subtotal);
        $this->assertEquals(300 + $order->delivery_fee, $order->total_amount);
        $this->assertEquals(150, $order->orderItems()->sole()->unit_price);
    }

    // ---- the admin setting -------------------------------------------------------

    public function test_a_changed_limit_is_enforced_everywhere(): void
    {
        BookingSettings::save(['max_quantity_per_menu_item' => 5]);
        $this->actingAs($this->customer());

        $this->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 6])
            ->assertSessionHas('error', 'Maximum 5 per item per order.');

        $this->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 4]);
        $this->post('/customer/cart/add', ['menu_item_id' => $this->adobo->id, 'quantity' => 4])
            ->assertSessionHas('error', 'Maximum 5 per item per order. You now have 5 × Adobo in your cart.');
        $this->assertSame(5, $this->cartQty());

        $this->post('/customer/cart/update/' . $this->key(), ['quantity' => 6])->assertSessionHas('error');
        $this->assertSame(5, $this->cartQty());

        $this->get('/customer/menu')->assertOk()->assertSee('\u0022max\u0022:5', false); // the stepper's limit
    }

    public function test_admin_can_set_the_limit_on_booking_rules(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get('/admin/booking-rules')
            ->assertOk()->assertSee('Max quantity per menu item per order');

        $this->actingAs($admin)->post('/admin/booking-rules', array_merge(
            array_map(fn ($rule) => $rule['default'], BookingSettings::RULES),
            ['max_quantity_per_menu_item' => 12]
        ))->assertSessionHasNoErrors();

        $this->assertSame(12, BookingSettings::get('max_quantity_per_menu_item'));
    }

    public function test_default_limit_is_20(): void
    {
        $this->assertSame(20, BookingSettings::get('max_quantity_per_menu_item'));
    }
}
