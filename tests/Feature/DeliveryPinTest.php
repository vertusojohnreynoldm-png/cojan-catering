<?php

namespace Tests\Feature;

use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliveryLocation;
use App\Services\ServiceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage A of the rider-map work: the customer's map pin on MENU checkout.
// Catering bookings / Avail / package checkout have no pin.
class DeliveryPinTest extends TestCase
{
    use RefreshDatabase;

    private const CAMINAWIT = [12.3318868, 121.082521];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer', 'email_verified_at' => now()], $attributes));
    }

    private function menuCart(bool $withPackage = false): void
    {
        $item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains'])->id,
            'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true,
        ]);
        $cart = ['menu_' . $item->id => ['id' => $item->id, 'name' => 'Adobo', 'price' => 150, 'quantity' => 1, 'type' => 'menu']];

        if ($withPackage) {
            $package = CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => 2000, 'is_available' => true]);
            $cart['package_' . $package->id] = ['id' => $package->id, 'name' => 'Fiesta', 'price' => 2000, 'quantity' => 1, 'type' => 'package'];
        }

        session(['cart' => $cart]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'delivery_phone' => '09171234567',
            'payment_method' => 'cash_on_delivery', 'delivery_type' => 'asap',
            'latitude' => self::CAMINAWIT[0], 'longitude' => self::CAMINAWIT[1], 'save_location' => '1',
        ], $overrides);
    }

    // ---- validation ----------------------------------------------------------

    public function test_menu_order_requires_a_pin(): void
    {
        $customer = $this->customer();
        $this->menuCart();

        $this->actingAs($customer)->post('/customer/orders', $this->payload(['latitude' => '', 'longitude' => '']))
            ->assertSessionHasErrors('latitude');

        $this->assertSame(0, Order::count());
    }

    public function test_pin_outside_san_jose_is_rejected(): void
    {
        $customer = $this->customer();

        foreach ([[14.5995, 120.9842], [13.4115, 121.1803], [12.0, 121.0]] as [$lat, $lng]) { // Manila, Calapan, just south of the box
            $this->menuCart();
            $this->actingAs($customer)->post('/customer/orders', $this->payload(['latitude' => $lat, 'longitude' => $lng]))
                ->assertSessionHasErrors(['latitude' => 'That map pin is outside San Jose, Occidental Mindoro — please move it to your delivery address.']);
        }

        $this->assertSame(0, Order::count());
    }

    public function test_bounding_box_is_the_osm_boundary_plus_about_one_kilometre(): void
    {
        $b = DeliveryLocation::menuBounds();

        $this->assertTrue(DeliveryLocation::isInsideMenuArea(12.0181627 - 0.005, 121.0)); // ~550 m outside the raw box: allowed
        $this->assertFalse(DeliveryLocation::isInsideMenuArea(12.0181627 - 0.02, 121.0));  // ~2 km outside: rejected
        $this->assertEqualsWithDelta(0.009, 12.0181627 - $b['south'], 0.0000001);
    }

    public function test_pin_is_saved_on_the_order_and_as_the_default(): void
    {
        $customer = $this->customer();
        $this->menuCart();

        $this->actingAs($customer)->post('/customer/orders', $this->payload())->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertEqualsWithDelta(self::CAMINAWIT[0], (float) $order->latitude, 0.0000001);
        $this->assertEqualsWithDelta(self::CAMINAWIT[1], (float) $order->longitude, 0.0000001);
        $customer->refresh();
        $this->assertEqualsWithDelta(self::CAMINAWIT[0], (float) $customer->default_latitude, 0.0000001);
    }

    public function test_unticked_save_location_keeps_the_old_default(): void
    {
        $customer = $this->customer(['default_latitude' => 12.35, 'default_longitude' => 121.06]);
        $this->menuCart();

        $this->actingAs($customer)->post('/customer/orders', $this->payload(['save_location' => '0']))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(12.35, (float) $customer->fresh()->default_latitude, 0.0000001);
    }

    public function test_mixed_cart_needs_a_pin_but_package_only_cart_does_not(): void
    {
        $customer = $this->customer();

        $this->menuCart(withPackage: true);
        $this->actingAs($customer)->post('/customer/orders', $this->payload(['latitude' => '', 'longitude' => '']))
            ->assertSessionHasErrors('latitude');

        $package = CateringPackage::create(['name' => 'Solo', 'pax' => 10, 'price' => 1500, 'is_available' => true]);
        session(['cart' => ['package_' . $package->id => ['id' => $package->id, 'name' => 'Solo', 'price' => 1500, 'quantity' => 1, 'type' => 'package']]]);
        $this->actingAs($customer)->post('/customer/orders', $this->payload([
            'province' => 'Oriental Mindoro', 'municipality' => 'Calapan City', 'barangay' => 'Balingayan',
            'latitude' => '', 'longitude' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(Order::sole()->latitude);
    }

    // ---- views -----------------------------------------------------------------

    public function test_menu_checkout_shows_the_map_prefilled_with_the_saved_pin(): void
    {
        $customer = $this->customer(['default_latitude' => 12.3601234, 'default_longitude' => 121.0701234]);
        $this->menuCart();

        $response = $this->actingAs($customer)->get('/customer/checkout')->assertOk();
        $response->assertSee('id="pin-map"', false)->assertSee('Use my current location')->assertSee('Save as my default location');
        $this->assertSame([12.3601234, 121.0701234], $response->viewData('pinMap')['saved']);
        $this->assertArrayHasKey('Caminawit', $response->viewData('pinMap')['barangays']);
    }

    public function test_package_flows_have_no_pin_map(): void
    {
        $customer = $this->customer();
        $package = CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => 2000, 'is_available' => true]);

        $this->actingAs($customer)->get('/customer/packages')->assertOk()->assertDontSee('pin-map', false);

        $this->actingAs($customer)->post('/customer/packages/avail', [
            'package_id' => $package->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'event_date' => '2026-11-20 15:00',
        ])->assertSessionHasNoErrors();
        $this->actingAs($customer)->get('/customer/packages/checkout')->assertOk()->assertDontSee('pin-map', false);

        // Package-only cart checkout (catering-style) — no map either.
        session(['cart' => ['package_' . $package->id => ['id' => $package->id, 'name' => 'Fiesta', 'price' => 2000, 'quantity' => 1, 'type' => 'package']]]);
        $this->actingAs($customer)->get('/customer/checkout')->assertOk()->assertDontSee('id="pin-map"', false);
    }

    // ---- fallback + data file ----------------------------------------------------

    public function test_old_orders_without_a_pin_fall_back_to_the_barangay_centre(): void
    {
        $order = Order::create([
            'user_id' => $this->customer()->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Caminawit',
        ]);

        $this->assertSame(['lat' => self::CAMINAWIT[0], 'lng' => self::CAMINAWIT[1], 'approximate' => true], DeliveryLocation::forOrder($order));

        $order->barangay = 'Not A Barangay';
        $this->assertSame(DeliveryLocation::townCenter(), [DeliveryLocation::forOrder($order)['lat'], DeliveryLocation::forOrder($order)['lng']]);

        $order->forceFill(['latitude' => 12.36, 'longitude' => 121.07]);
        $this->assertFalse(DeliveryLocation::forOrder($order)['approximate']);
    }

    public function test_data_file_has_an_entry_for_every_deliverable_barangay_inside_the_box(): void
    {
        $data = require database_path('data/barangay_coords.php');

        foreach (ServiceArea::menuBarangays() as $barangay) {
            $this->assertArrayHasKey($barangay, $data['barangays'], "$barangay missing from barangay_coords.php");
            $point = $data['barangays'][$barangay] ?? DeliveryLocation::townCenter();
            $this->assertTrue(DeliveryLocation::isInsideMenuArea(...$point), "$barangay centre is outside the box");
        }
    }

    public function test_fetch_command_follows_the_nominatim_policy(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::sequence()
                ->push([['lat' => '12.35', 'lon' => '121.06', 'addresstype' => 'town', 'boundingbox' => ['12.0', '12.7', '120.8', '121.3']]])
                ->whenEmpty(Http::response([['lat' => '12.36', 'lon' => '121.07', 'addresstype' => 'village']])),
        ]);

        Sleep::fake();

        $this->artisan('geo:fetch-barangay-coords', ['--dry-run' => true])->assertSuccessful();

        // At least 1.1 s between consecutive requests (1 request/second).
        Sleep::assertSleptTimes(count(ServiceArea::menuBarangays()));
        Sleep::assertSequence(array_fill(0, count(ServiceArea::menuBarangays()), Sleep::usleep(1_100_000)));

        Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->header('User-Agent')[0] ?? '', 'CojanCatering/')
            && $r['countrycodes'] === 'ph' && $r['limit'] === 1);
        Http::assertSentCount(1 + count(ServiceArea::menuBarangays()));
    }
}
