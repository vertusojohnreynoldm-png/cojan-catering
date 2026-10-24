<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage D: the customer's tracking page for MENU orders — live rider
// position + route from the SERVER's stored rider fix, the 4-step stepper
// and the final states. Bookings keep their old timeline and get no map.
class CustomerTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const PIN = [12.3697, 121.0952];
    private const RIDER = [12.3540, 121.0675];

    private function order(User $customer, array $attrs = [], ?string $deliveryStatus = 'in_transit', array $deliveryAttrs = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'out_for_delivery', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Mabini',
            'latitude' => self::PIN[0], 'longitude' => self::PIN[1],
        ], $attrs));

        if ($deliveryStatus) {
            Delivery::create(array_merge([
                'order_id' => $order->id,
                'user_id' => User::factory()->create(['role' => 'delivery'])->id,
                'status' => $deliveryStatus,
                'current_lat' => self::RIDER[0], 'current_lng' => self::RIDER[1], 'current_accuracy' => 12,
                'current_heading' => 45, 'current_speed' => 5, 'last_location_update' => now()->subSeconds(20),
            ], $deliveryAttrs));
        }

        return $order->fresh('delivery');
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function osrmOk(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['distance' => 4132, 'duration' => 441, 'geometry' => ['coordinates' => [[121.0675, 12.354], [121.0952, 12.3697]]]]],
        ])]);
    }

    // ---- live endpoint -------------------------------------------------------------

    public function test_owner_gets_the_stored_rider_fix_and_the_route(): void
    {
        $this->osrmOk();
        $customer = $this->customer();
        $order = $this->order($customer);

        $json = $this->actingAs($customer)->getJson("/track/{$order->order_number}/live")->assertOk()->json();

        $this->assertTrue($json['active']);
        $this->assertNull($json['final']);
        $this->assertSame(self::RIDER[0], $json['rider']['lat']);
        $this->assertEquals(45, $json['rider']['heading']);
        $this->assertEqualsWithDelta(20, $json['rider']['age_seconds'], 2);
        $this->assertSame(4132, $json['route']['distance_m']);
        $this->assertSame(['lat' => self::PIN[0], 'lng' => self::PIN[1], 'approximate' => false], $json['destination']);
    }

    public function test_browser_coordinates_are_ignored(): void
    {
        $this->osrmOk();
        $customer = $this->customer();
        $order = $this->order($customer);

        // Try to make the server route from somewhere else entirely.
        $this->actingAs($customer)->getJson("/track/{$order->order_number}/live?lat=14.5995&lng=120.9842")->assertOk();

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '121.067500,12.354000;121.095200,12.369700'));
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), '120.98'));
    }

    public function test_other_customers_riders_and_guests_cannot_track(): void
    {
        $this->osrmOk();
        $order = $this->order($this->customer());

        $this->actingAs($this->customer())->getJson("/track/{$order->order_number}/live")->assertNotFound();
        $this->actingAs(User::find($order->delivery->user_id))->get("/track/{$order->order_number}/live")->assertRedirect(); // role:customer

        auth()->logout();
        $this->getJson("/track/{$order->order_number}/live")->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_not_yet_on_the_way_has_no_rider_or_route(): void
    {
        $customer = $this->customer();
        Http::fake();

        foreach ([['status' => 'pending'], ['status' => 'preparing']] as $attrs) {
            $order = $this->order($customer, $attrs, 'assigned');
            $this->actingAs($customer)->getJson("/track/{$order->order_number}/live")
                ->assertOk()
                ->assertJson(['active' => false, 'final' => null, 'rider' => null, 'route' => null]);
        }

        $noDelivery = $this->order($customer, ['status' => 'confirmed'], null);
        $this->actingAs($customer)->getJson("/track/{$noDelivery->order_number}/live")->assertJson(['active' => false, 'rider' => null]);

        Http::assertNothingSent();
    }

    public function test_finished_orders_report_the_final_state_and_no_location(): void
    {
        $customer = $this->customer();
        Http::fake();

        $delivered = $this->order($customer, ['status' => 'delivered'], 'delivered');
        $this->actingAs($customer)->getJson("/track/{$delivered->order_number}/live")
            ->assertJson(['active' => false, 'final' => 'delivered', 'rider' => null, 'route' => null]);

        $cancelled = $this->order($customer, ['status' => 'cancelled'], 'in_transit');
        $this->actingAs($customer)->getJson("/track/{$cancelled->order_number}/live")
            ->assertJson(['active' => false, 'final' => 'cancelled', 'rider' => null]);

        Http::assertNothingSent();
    }

    public function test_bookings_have_no_live_tracking(): void
    {
        $customer = $this->customer();
        $booking = $this->order($customer, ['event_date' => now()->addDays(5), 'latitude' => null, 'longitude' => null], null);

        $this->actingAs($customer)->getJson("/track/{$booking->order_number}/live")->assertNotFound();
    }

    public function test_live_endpoint_shares_the_per_user_rate_limit(): void
    {
        $this->osrmOk();
        $customer = $this->customer();
        $order = $this->order($customer);

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($customer)->getJson("/track/{$order->order_number}/live")->assertOk();
        }
        $this->actingAs($customer)->getJson("/track/{$order->order_number}/live")->assertStatus(429);
    }

    public function test_age_is_computed_on_the_server_for_the_stale_warning(): void
    {
        $this->osrmOk();
        $customer = $this->customer();
        $order = $this->order($customer, [], 'in_transit', ['last_location_update' => now()->subSeconds(150)]);

        $json = $this->actingAs($customer)->getJson("/track/{$order->order_number}/live")->json();

        $this->assertGreaterThanOrEqual(150, $json['rider']['age_seconds']); // page shows "Location stale" at >= 120 s
    }

    // ---- page ----------------------------------------------------------------------------

    public function test_on_the_way_page_shows_stepper_map_and_live_bits(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $this->actingAs($customer)->get("/track/{$order->order_number}")
            ->assertOk()
            ->assertSeeInOrder(['Confirmed', 'Preparing', 'Out for Delivery', 'Delivered'])
            ->assertSee('aria-current="step"', false)
            ->assertSee('id="track-map"', false)
            ->assertSee('id="track-stale"', false)
            ->assertSee('\/track\/' . $order->order_number . '\/live', false) // Js::from escapes slashes
            ->assertDontSee('Waiting for confirmation');
    }

    public function test_pending_order_says_waiting_for_confirmation(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer, ['status' => 'pending'], null);

        $this->actingAs($customer)->get("/track/{$order->order_number}")
            ->assertOk()
            ->assertSee('Waiting for confirmation')
            ->assertDontSee('aria-current="step"', false);
    }

    public function test_delivered_and_cancelled_pages_show_the_final_state_without_live_tracking(): void
    {
        $customer = $this->customer();

        $delivered = $this->order($customer, ['status' => 'delivered'], 'delivered', ['delivered_at' => now()]);
        $this->actingAs($customer)->get("/track/{$delivered->order_number}")
            ->assertOk()
            ->assertSee('Live tracking has ended')
            ->assertDontSee('id="track-map"', false)
            ->assertDontSee('leaflet.js', false)
            ->assertDontSee((string) self::RIDER[0]);

        $cancelled = $this->order($customer, ['status' => 'cancelled'], 'in_transit');
        $this->actingAs($customer)->get("/track/{$cancelled->order_number}")
            ->assertOk()
            ->assertSee('This order was cancelled.')
            ->assertDontSee('id="track-map"', false);
    }

    public function test_booking_page_keeps_the_old_timeline_and_no_map(): void
    {
        $customer = $this->customer();
        $booking = $this->order($customer, ['event_date' => now()->addDays(5), 'status' => 'confirmed', 'latitude' => null, 'longitude' => null], null);

        $this->actingAs($customer)->get("/track/{$booking->order_number}")
            ->assertOk()
            ->assertSee('cj-timeline', false)
            ->assertDontSee('cj-progress', false)
            ->assertDontSee('id="track-map"', false);
    }
}
