<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\RouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage B: rider navigation map + routing for MENU-order deliveries.
class RiderNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const PIN = [12.3697, 121.0952];      // customer (Mabini)
    private const RIDER = [12.3540, 121.0675];    // rider (town centre)

    private function rider(): User
    {
        return User::factory()->create(['role' => 'delivery', 'email_verified_at' => now()]);
    }

    private function delivery(User $rider, array $order = [], string $status = 'picked_up'): Delivery
    {
        $o = Order::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'customer', 'name' => 'Ana Reyes'])->id,
            'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'out_for_delivery', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => '45 Rizal St, Mabini, San Jose', 'delivery_phone' => '09171234567',
            'street' => '45 Rizal St', 'barangay' => 'Mabini', 'landmark' => 'Blue gate near the chapel',
            'latitude' => self::PIN[0], 'longitude' => self::PIN[1],
        ], $order));

        return Delivery::create(['order_id' => $o->id, 'user_id' => $rider->id, 'status' => $status, 'assigned_at' => now()]);
    }

    private function osrmOk(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [[
                'distance' => 4132.4, 'duration' => 441.6,
                'geometry' => ['type' => 'LineString', 'coordinates' => [[121.0675, 12.3540], [121.08, 12.36], [121.0952, 12.3697]]],
            ]],
        ])]);
    }

    private function routeUrl(Delivery $d): string
    {
        return "/delivery/orders/{$d->id}/route?lat=" . self::RIDER[0] . '&lng=' . self::RIDER[1];
    }

    // ---- route endpoint --------------------------------------------------------

    public function test_assigned_rider_gets_a_road_route_from_osrm(): void
    {
        $this->osrmOk();
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->getJson($this->routeUrl($d))
            ->assertOk()
            ->assertJson([
                'provider' => 'osrm', 'fallback' => false, 'distance_m' => 4132, 'duration_s' => 442,
                'geometry' => [[12.354, 121.0675], [12.36, 121.08], [12.3697, 121.0952]],
                'destination' => ['lat' => self::PIN[0], 'lng' => self::PIN[1], 'approximate' => false],
            ]);

        // OSRM wants lng,lat order in the path.
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/route/v1/driving/121.067500,12.354000;121.095200,12.369700'));
    }

    public function test_routing_failure_falls_back_to_a_straight_line(): void
    {
        Http::fake(['*' => Http::response('down', 503)]);
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $json = $this->actingAs($rider)->getJson($this->routeUrl($d))->assertOk()->json();

        $this->assertTrue($json['fallback']);
        $this->assertSame('straight-line', $json['provider']);
        $this->assertSame([[self::RIDER[0], self::RIDER[1]], [self::PIN[0], self::PIN[1]]], $json['geometry']);
        $this->assertEqualsWithDelta(RouteService::haversine(self::RIDER, self::PIN), $json['distance_m'], 1);
        $this->assertGreaterThan(0, $json['duration_s']);
    }

    public function test_ors_is_used_when_a_key_is_set_and_osrm_backs_it_up(): void
    {
        config(['services.routing.ors_key' => 'test-key']);
        Http::fake([
            'api.openrouteservice.org/*' => Http::response([
                'features' => [[
                    'geometry' => ['coordinates' => [[121.0675, 12.354], [121.0952, 12.3697]]],
                    'properties' => ['summary' => ['distance' => 3900, 'duration' => 420]],
                ]],
            ]),
        ]);
        $rider = $this->rider();

        $json = $this->actingAs($rider)->getJson($this->routeUrl($this->delivery($rider)))->json();
        $this->assertSame('ors', $json['provider']);
        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'test-key'));
    }

    public function test_osrm_takes_over_when_ors_is_down(): void
    {
        config(['services.routing.ors_key' => 'test-key']);
        Http::fake([
            'api.openrouteservice.org/*' => Http::response('down', 503),
            'router.project-osrm.org/*' => Http::response([
                'code' => 'Ok',
                'routes' => [['distance' => 4132, 'duration' => 441, 'geometry' => ['coordinates' => [[121.0675, 12.354], [121.0952, 12.3697]]]]],
            ]),
        ]);
        $rider = $this->rider();

        $json = $this->actingAs($rider)->getJson($this->routeUrl($this->delivery($rider)))->json();

        $this->assertSame('osrm', $json['provider']);
        Http::assertSentCount(2); // tried ORS first, then OSRM
    }

    public function test_routes_are_cached_for_a_minute(): void
    {
        $this->osrmOk();
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->getJson($this->routeUrl($d))->assertOk();
        $this->actingAs($rider)->getJson("/delivery/orders/{$d->id}/route?lat=12.35401&lng=121.06752")->assertOk(); // ~2 m away

        Http::assertSentCount(1);
    }

    public function test_only_the_assigned_rider_can_get_the_route(): void
    {
        $this->osrmOk();
        $d = $this->delivery($this->rider());

        $this->actingAs($this->rider())->getJson($this->routeUrl($d))->assertNotFound();

        $customer = $d->order->user;
        $this->actingAs($customer)->get($this->routeUrl($d))->assertRedirect(); // role:delivery bounces customers

        auth()->logout();
        $this->getJson($this->routeUrl($d))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_no_route_once_the_delivery_is_finished_or_cancelled_or_for_bookings(): void
    {
        $this->osrmOk();
        $rider = $this->rider();

        foreach ([
            $this->delivery($rider, [], 'delivered'),
            $this->delivery($rider, [], 'failed'),
            $this->delivery($rider, ['status' => 'cancelled']),
            $this->delivery($rider, ['event_date' => now()->addDays(5)]), // a catering booking
        ] as $d) {
            $this->actingAs($rider)->getJson($this->routeUrl($d))->assertForbidden()->assertJsonMissing(['destination']);
        }

        Http::assertNothingSent();
    }

    public function test_route_endpoint_is_rate_limited_per_rider(): void
    {
        $this->osrmOk();
        $rider = $this->rider();
        $d = $this->delivery($rider);

        for ($i = 0; $i < 12; $i++) {
            $this->actingAs($rider)->getJson($this->routeUrl($d))->assertOk();
        }
        $this->actingAs($rider)->getJson($this->routeUrl($d))->assertStatus(429);

        // Another rider has their own allowance.
        $other = $this->rider();
        $this->actingAs($other)->getJson($this->routeUrl($this->delivery($other)))->assertOk();
    }

    public function test_route_rejects_bad_coordinates(): void
    {
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->getJson("/delivery/orders/{$d->id}/route?lat=999&lng=abc")->assertStatus(422);
    }

    // ---- rider page --------------------------------------------------------------

    public function test_active_menu_delivery_page_has_map_navigate_waze_and_call(): void
    {
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->get("/delivery/orders/{$d->id}")
            ->assertOk()
            ->assertSee('id="rider-map"', false)
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=12.3697000,121.0952000&amp;travelmode=driving', false)
            ->assertSee('https://waze.com/ul?ll=12.3697000,121.0952000&amp;navigate=yes', false)
            ->assertSee('href="tel:09171234567"', false)
            ->assertSee('Blue gate near the chapel')
            ->assertDontSee('Location approximate');
    }

    public function test_pinless_old_order_shows_the_approximate_note(): void
    {
        $rider = $this->rider();
        $d = $this->delivery($rider, ['latitude' => null, 'longitude' => null]);

        $this->actingAs($rider)->get("/delivery/orders/{$d->id}")
            ->assertOk()
            ->assertSee('Location approximate')
            ->assertSee('destination=12.3697063,121.0951758', false); // Mabini centre from barangay_coords.php
    }

    public function test_finished_delivery_page_never_contains_the_customer_location(): void
    {
        $rider = $this->rider();

        foreach (['delivered', 'failed'] as $status) {
            $d = $this->delivery($rider, [], $status);
            $this->actingAs($rider)->get("/delivery/orders/{$d->id}")
                ->assertOk()
                ->assertDontSee('rider-map', false)
                ->assertDontSee('12.3697')
                ->assertDontSee('google.com/maps/dir', false);
        }
    }

    public function test_admin_sees_the_customer_pin_but_bookings_have_none(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $menu = $this->delivery($this->rider())->order;
        $booking = $this->delivery($this->rider(), ['event_date' => now()->addDays(5), 'latitude' => null, 'longitude' => null])->order;

        $this->actingAs($admin)->get("/admin/orders/{$menu->id}")->assertOk()->assertSee('id="admin-pin-map"', false);
        $this->actingAs($admin)->get("/admin/orders/{$booking->id}")->assertOk()->assertDontSee('admin-pin-map', false);
    }
}
