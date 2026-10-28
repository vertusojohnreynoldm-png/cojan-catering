<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\QrHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tests\TestCase;

// "QR verified at hand-off": the ASSIGNED rider scans the customer's order
// QR (its /track link) — by phone camera or the in-app scanner — while the
// menu order is picked up / in transit. Optional for marking Delivered.
class QrHandoffTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['code' => 'NoRoute'], 200)]);
        $this->customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->rider = User::factory()->create(['role' => 'delivery']);
    }

    private function order(string $status = 'out_for_delivery', ?string $deliveryStatus = 'picked_up', array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => $status, 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Mabini',
            'latitude' => 12.3697, 'longitude' => 121.0952, 'qr_code' => '<svg></svg>',
        ], $attrs));

        if ($deliveryStatus) {
            Delivery::create(['order_id' => $order->id, 'user_id' => $this->rider->id, 'status' => $deliveryStatus]);
        }

        return $order->fresh('delivery');
    }

    private function link(Order $order): string
    {
        return route('customer.orders.track', $order->order_number);
    }

    private function logs(): int
    {
        return ActivityLog::where('action', 'delivery.qr_verified')->count();
    }

    // ---- phone camera: the /track link -----------------------------------------------

    public function test_assigned_rider_scan_records_the_handoff_and_opens_the_rider_page(): void
    {
        foreach (['picked_up', 'in_transit'] as $status) {
            $order = $this->order(deliveryStatus: $status);

            $this->actingAs($this->rider)->get($this->link($order))
                ->assertRedirect(route('delivery.orders.show', $order->delivery->id))
                ->assertSessionHas('qr_verified', true);

            $d = $order->delivery->fresh();
            $this->assertNotNull($d->qr_scanned_at);
            $this->assertSame($this->rider->id, $d->qr_scanned_by);
            $this->assertNull($d->qr_scan_lat); // a link carries no GPS
        }

        $this->assertSame(2, $this->logs());
        $log = ActivityLog::where('action', 'delivery.qr_verified')->first();
        $this->assertSame($this->rider->id, $log->actor_id);
        $this->assertStringContainsString('(phone camera)', $log->description);
    }

    public function test_rider_page_shows_the_banner_after_a_scan(): void
    {
        $order = $this->order();

        $this->actingAs($this->rider)->followingRedirects()->get($this->link($order))->assertOk()
            ->assertSee('QR verified</strong> — take the proof photo and mark Delivered.', false)
            ->assertSee('QR verified at hand-off at ' . $order->delivery->fresh()->qr_scanned_at->format('g:i A'))
            ->assertSee(Js::from(route('delivery.orders.qr-location', $order->delivery->id))->toHtml(), false)
            ->assertDontSee('Scan customer QR');
    }

    public function test_a_second_scan_keeps_the_first_time_and_logs_once(): void
    {
        $order = $this->order();
        $this->actingAs($this->rider)->get($this->link($order));
        $first = $order->delivery->fresh()->qr_scanned_at;

        $this->travel(3)->minutes();
        $this->actingAs($this->rider)->get($this->link($order))->assertRedirect(route('delivery.orders.show', $order->delivery->id));

        $this->assertEquals($first, $order->delivery->fresh()->qr_scanned_at);
        $this->assertSame(1, $this->logs());
    }

    public function test_another_rider_learns_nothing_and_nothing_is_recorded(): void
    {
        $order = $this->order();
        $other = User::factory()->create(['role' => 'delivery']);

        $this->actingAs($other)->get($this->link($order))
            ->assertRedirect(route('delivery.dashboard'))->assertSessionMissing('error')->assertSessionMissing('qr_verified');
        $this->actingAs($other)->get('/track/ORD-NOSUCH00')
            ->assertRedirect(route('delivery.dashboard'))->assertSessionMissing('error');

        $this->assertNull($order->delivery->fresh()->qr_scanned_at);
        $this->assertSame(0, $this->logs());
    }

    public function test_customer_admin_and_guest_keep_the_old_behaviour(): void
    {
        $order = $this->order();

        $this->actingAs($this->customer)->get($this->link($order))->assertOk()->assertSee('Order Tracking');
        $stranger = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->actingAs($stranger)->get($this->link($order))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get($this->link($order))->assertRedirect(route('admin.dashboard'));
        auth()->logout();
        $this->get($this->link($order))->assertRedirect(route('login'));

        $this->assertNull($order->delivery->fresh()->qr_scanned_at);
        $this->assertSame(0, $this->logs());
    }

    public function test_scan_too_early_or_after_the_order_is_finished_is_refused(): void
    {
        $cases = [
            'Too early' => $this->order(deliveryStatus: 'assigned'),
            'already delivered' => $this->order('delivered', 'delivered'),
            'was cancelled' => $this->order('cancelled', 'in_transit'),
            'marked as failed' => $this->order(deliveryStatus: 'failed'),
        ];

        foreach ($cases as $phrase => $order) {
            $this->actingAs($this->rider)->get($this->link($order))
                ->assertRedirect(route('delivery.dashboard'))
                ->assertSessionHas('error', fn ($m) => str_contains($m, $phrase));
            $this->assertNull($order->delivery->fresh()->qr_scanned_at, $phrase);
        }
        $this->assertSame(0, $this->logs());
    }

    public function test_booking_qr_is_unchanged_for_riders(): void
    {
        $booking = $this->order('confirmed', 'picked_up', ['event_date' => now()->addDays(5), 'latitude' => null, 'longitude' => null]);

        $this->actingAs($this->rider)->get($this->link($booking))->assertRedirect(route('delivery.dashboard'));
        $this->assertNull($booking->delivery->fresh()->qr_scanned_at);
    }

    public function test_gps_is_attached_after_a_phone_camera_scan_once_and_only_shortly_after(): void
    {
        $order = $this->order();
        $url = route('delivery.orders.qr-location', $order->delivery->id);

        // Nothing to attach to before a scan.
        $this->actingAs($this->rider)->postJson($url, ['lat' => 12.36, 'lng' => 121.09])->assertStatus(409);

        $this->actingAs($this->rider)->get($this->link($order));
        $this->actingAs($this->rider)->postJson($url, ['lat' => 12.36, 'lng' => 121.09, 'accuracy' => 8])->assertOk();
        $this->actingAs($this->rider)->postJson($url, ['lat' => 1, 'lng' => 1])->assertStatus(409); // never overwritten

        $d = $order->delivery->fresh();
        $this->assertEqualsWithDelta(12.36, $d->qr_scan_lat, 0.0001);
        $this->assertEquals(8, $d->qr_scan_accuracy);

        $late = $this->order();
        $this->actingAs($this->rider)->get($this->link($late));
        $this->travel(QrHandoff::POSITION_WINDOW_MINUTES + 1)->minutes();
        $this->actingAs($this->rider)->postJson(route('delivery.orders.qr-location', $late->delivery->id), ['lat' => 12.36, 'lng' => 121.09])->assertStatus(409);

        $other = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($other)->postJson($url, ['lat' => 12.36, 'lng' => 121.09])->assertNotFound();
    }

    // ---- in-app scanner ------------------------------------------------------------------

    public function test_in_app_scan_of_this_orders_qr_verifies_with_gps(): void
    {
        $order = $this->order(deliveryStatus: 'in_transit');

        $this->actingAs($this->rider)->postJson(route('delivery.orders.verify-qr', $order->delivery->id), [
            'code' => $this->link($order), 'lat' => 12.355, 'lng' => 121.068, 'accuracy' => 15,
        ])->assertOk()->assertJson(['success' => true, 'redirect' => route('delivery.orders.show', $order->delivery->id)])
            ->assertSessionHas('qr_verified', true);

        $d = $order->delivery->fresh();
        $this->assertNotNull($d->qr_scanned_at);
        $this->assertEqualsWithDelta(12.355, $d->qr_scan_lat, 0.0001);
        $this->assertStringContainsString('(in-app scanner)', ActivityLog::where('action', 'delivery.qr_verified')->value('description'));
    }

    public function test_in_app_scan_accepts_http_or_https_and_a_trailing_slash(): void
    {
        $order = $this->order();
        $code = preg_replace('#^https?://#', 'https://', $this->link($order)) . '/';

        $this->actingAs($this->rider)->postJson(route('delivery.orders.verify-qr', $order->delivery->id), ['code' => $code])->assertOk();
    }

    public function test_in_app_scan_rejects_any_other_qr(): void
    {
        $order = $this->order();
        $otherOrder = $this->order();
        $path = parse_url($this->link($order), PHP_URL_PATH);

        foreach ([
            $this->link($otherOrder),                       // another order's QR
            'https://evil.example.com' . $path,             // right path, wrong site
            $order->order_number,                           // not a link
            'javascript:alert(1)',
            str_replace($order->order_number, strtolower($order->order_number), $this->link($order)),
        ] as $code) {
            $this->actingAs($this->rider)->postJson(route('delivery.orders.verify-qr', $order->delivery->id), ['code' => $code])
                ->assertStatus(422)->assertJson(['message' => "That QR code isn't for Order #{$order->order_number}. Ask the customer to open this order's QR."]);
        }

        $this->assertNull($order->delivery->fresh()->qr_scanned_at);
        $this->assertSame(0, $this->logs());
    }

    public function test_in_app_scan_outside_the_window_or_by_another_rider_is_refused(): void
    {
        $early = $this->order(deliveryStatus: 'assigned');
        $this->actingAs($this->rider)->postJson(route('delivery.orders.verify-qr', $early->delivery->id), ['code' => $this->link($early)])
            ->assertStatus(409)->assertJson(['closed' => true])->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Too early'));

        $done = $this->order('delivered', 'delivered');
        $this->actingAs($this->rider)->postJson(route('delivery.orders.verify-qr', $done->delivery->id), ['code' => $this->link($done)])
            ->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'already delivered'));

        $active = $this->order();
        $other = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($other)->postJson(route('delivery.orders.verify-qr', $active->delivery->id), ['code' => $this->link($active)])->assertNotFound();

        $this->assertSame(0, $this->logs());
    }

    public function test_scan_button_only_shows_while_the_rider_has_the_order(): void
    {
        $assigned = $this->order(deliveryStatus: 'assigned');
        $this->actingAs($this->rider)->get(route('delivery.orders.show', $assigned->delivery->id))->assertOk()->assertDontSee('Scan customer QR');

        $active = $this->order();
        $this->actingAs($this->rider)->get(route('delivery.orders.show', $active->delivery->id))->assertOk()
            ->assertSee('Scan customer QR')
            ->assertSee(Js::from(parse_url($this->link($active), PHP_URL_PATH))->toHtml(), false);
    }

    // ---- optional for Delivered; admin sees the result -------------------------------

    public function test_delivering_without_a_scan_still_works_and_admin_sees_it_flagged(): void
    {
        Storage::fake('public');
        $order = $this->order();

        $this->actingAs($this->rider)->post(route('delivery.orders.status', $order->delivery->id), [
            'status' => 'delivered', 'photo' => UploadedFile::fake()->image('p.jpg'),
        ])->assertRedirect();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertTrue($order->delivery->fresh()->deliveredWithoutQr());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.orders.show', $order->id))->assertOk()
            ->assertSee('Delivered without QR verification')->assertDontSee('QR verified at hand-off');
    }

    public function test_verified_handoff_shows_to_admin_customer_and_rider_after_delivery(): void
    {
        Storage::fake('public');
        $order = $this->order();
        $this->actingAs($this->rider)->get($this->link($order));
        $this->actingAs($this->rider)->postJson(route('delivery.orders.qr-location', $order->delivery->id), ['lat' => 12.36, 'lng' => 121.09, 'accuracy' => 8]);
        $this->actingAs($this->rider)->post(route('delivery.orders.status', $order->delivery->id), [
            'status' => 'delivered', 'photo' => UploadedFile::fake()->image('p.jpg'),
        ]);
        $time = 'QR verified at hand-off at ' . $order->delivery->fresh()->qr_scanned_at->format('g:i A');

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.orders.show', $order->id))->assertOk()
            ->assertSee($time)->assertSee($this->rider->name)->assertSee('12.36000, 121.09000')
            ->assertDontSee('Delivered without QR verification');
        $this->actingAs($this->customer)->get(route('customer.orders.show', $order->id))->assertOk()->assertSee($time);
        $this->actingAs($this->rider)->get(route('delivery.orders.show', $order->delivery->id))->assertOk()->assertSee($time);
    }

    public function test_bookings_never_show_the_unverified_flag(): void
    {
        $booking = $this->order('delivered', 'delivered', ['event_date' => now()->subDay(), 'latitude' => null, 'longitude' => null]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.orders.show', $booking->id))->assertOk()
            ->assertDontSee('Delivered without QR verification');
    }
}
