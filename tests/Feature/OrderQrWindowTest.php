<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// Menu orders: the order QR is only printed while the rider has the order
// (delivery picked up / in transit). Before that a note shows, after it the
// final state — and the rider's scan / find-order refuses outside the window.
class OrderQrWindowTest extends TestCase
{
    use RefreshDatabase;

    private const QR = '<svg data-test-qr="1"></svg>';

    private User $customer;
    private User $rider;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['code' => 'NoRoute'], 200)]); // routing → straight-line fallback
        $this->customer = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $this->rider = User::factory()->create(['role' => 'delivery']);
    }

    private function order(string $status, ?string $deliveryStatus = null, array $attrs = []): Order
    {
        $order = Order::create(array_merge([
            'user_id' => $this->customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => $status, 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Mabini',
            'latitude' => 12.3697, 'longitude' => 121.0952, 'qr_code' => self::QR,
        ], $attrs));

        if ($deliveryStatus) {
            Delivery::create([
                'order_id' => $order->id, 'user_id' => $this->rider->id, 'status' => $deliveryStatus,
                'current_lat' => 12.354, 'current_lng' => 121.0675, 'last_location_update' => now(),
            ]);
        }

        return $order->fresh('delivery');
    }

    /** [order status, delivery status, expected stage, text the customer sees] */
    public static function stages(): array
    {
        $note = 'Your QR code will appear once the rider picks up your order.';

        return [
            'pending'    => ['pending', null, 'waiting', $note],
            'confirmed'  => ['confirmed', null, 'waiting', $note],
            'preparing'  => ['preparing', null, 'waiting', $note],
            'assigned'   => ['out_for_delivery', 'assigned', 'waiting', $note],
            'picked up'  => ['out_for_delivery', 'picked_up', 'active', 'Your rider has your order.'],
            'in transit' => ['out_for_delivery', 'in_transit', 'active', 'Your rider has your order.'],
            'delivered'  => ['delivered', 'delivered', 'delivered', 'no QR code is needed anymore'],
            'cancelled'  => ['cancelled', null, 'cancelled', "there's no QR code for it"],
            'cancelled after pickup' => ['cancelled', 'in_transit', 'cancelled', "there's no QR code for it"],
            'failed'     => ['out_for_delivery', 'failed', 'failed', "couldn't complete this delivery"],
        ];
    }

    #[DataProvider('stages')]
    public function test_order_detail_shows_the_qr_only_while_the_rider_has_it(string $status, ?string $delivery, string $stage, string $text): void
    {
        $order = $this->order($status, $delivery);
        $this->assertSame($stage, $order->qrStage());

        $page = $this->actingAs($this->customer)->get(route('customer.orders.show', $order->id))->assertOk();
        $page->assertSee($text, false);
        $page->assertSee('data-qr-stage="' . $stage . '"', false);

        if ($stage === 'active') {
            $page->assertSee(self::QR, false);
        } else {
            $page->assertDontSee('data-test-qr', false);
        }
    }

    #[DataProvider('stages')]
    public function test_tracking_page_shows_the_qr_only_while_the_rider_has_it(string $status, ?string $delivery, string $stage, string $text): void
    {
        $order = $this->order($status, $delivery);

        $page = $this->actingAs($this->customer)->get(route('customer.orders.track', $order->order_number))->assertOk();
        $page->assertSee($text, false);

        if ($stage === 'active') {
            $page->assertSee(self::QR, false);
        } else {
            $page->assertDontSee('data-test-qr', false);
        }
    }

    public function test_order_detail_polls_only_while_the_window_can_still_change(): void
    {
        $waiting = $this->order('preparing');
        $this->actingAs($this->customer)->get(route('customer.orders.show', $waiting->id))
            ->assertSee(Js::from(route('customer.orders.track.live', $waiting->order_number))->toHtml(), false);

        $done = $this->order('delivered', 'delivered');
        $this->actingAs($this->customer)->get(route('customer.orders.show', $done->id))
            ->assertDontSee(Js::from(route('customer.orders.track.live', $done->order_number))->toHtml(), false);
    }

    public function test_live_endpoint_flags_when_the_window_opens(): void
    {
        $order = $this->order('out_for_delivery', 'assigned');
        $this->actingAs($this->customer)->getJson(route('customer.orders.track.live', $order->order_number))
            ->assertOk()->assertJson(['active' => false, 'final' => null])->assertJsonMissingPath('qr_code');

        $order->delivery->update(['status' => 'picked_up']);
        $this->actingAs($this->customer)->getJson(route('customer.orders.track.live', $order->order_number))
            ->assertOk()->assertJson(['active' => true]);
    }

    public function test_qr_never_leaks_through_serialization(): void
    {
        $order = $this->order('out_for_delivery', 'in_transit');

        $this->assertArrayNotHasKey('qr_code', $order->toArray());
        $this->assertStringNotContainsString('data-test-qr', $order->toJson());
    }

    public function test_admin_sees_the_qr_only_inside_the_window_for_menu_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $waiting = $this->order('out_for_delivery', 'assigned');
        $this->actingAs($admin)->get(route('admin.orders.show', $waiting->id))->assertOk()
            ->assertSee('Hidden until the rider picks up the order.')->assertDontSee('data-test-qr', false);

        $done = $this->order('delivered', 'delivered');
        $this->actingAs($admin)->get(route('admin.orders.show', $done->id))->assertOk()
            ->assertSee('this order is delivered')->assertDontSee('data-test-qr', false);

        $active = $this->order('out_for_delivery', 'picked_up');
        $this->actingAs($admin)->get(route('admin.orders.show', $active->id))->assertOk()->assertSee(self::QR, false);
    }

    public function test_bookings_are_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->order('confirmed', null, ['event_date' => now()->addDays(5), 'latitude' => null, 'longitude' => null]);

        // Customer: still no QR card at all on a booking. Admin: still shown.
        $this->actingAs($this->customer)->get(route('customer.orders.show', $booking->id))->assertOk()
            ->assertDontSee('data-qr-stage', false)->assertDontSee('data-test-qr', false);
        $this->actingAs($admin)->get(route('admin.orders.show', $booking->id))->assertOk()->assertSee(self::QR, false);
    }

    // ---- rider scan / find order ---------------------------------------------------

    private function scan(Order $order, ?User $rider = null)
    {
        return $this->actingAs($rider ?? $this->rider)->post(route('delivery.scan'), ['order_number' => $order->order_number]);
    }

    public function test_scan_works_while_the_rider_has_the_order(): void
    {
        foreach (['picked_up', 'in_transit'] as $status) {
            $order = $this->order('out_for_delivery', $status);
            $this->scan($order)->assertRedirect(route('delivery.orders.show', $order->delivery->id))->assertSessionMissing('error');
        }
    }

    public function test_scan_before_pickup_is_refused_as_too_early(): void
    {
        $order = $this->order('out_for_delivery', 'assigned');

        $this->scan($order)->assertRedirect(route('delivery.dashboard'))
            ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'Too early') && str_contains($m, 'mark it Picked Up first'));
    }

    public function test_scan_after_the_order_is_finished_is_refused(): void
    {
        $cases = [
            'already delivered' => $this->order('delivered', 'delivered'),
            'was cancelled' => $this->order('cancelled', 'in_transit'),
            'marked as failed' => $this->order('out_for_delivery', 'failed'),
        ];

        foreach ($cases as $phrase => $order) {
            $this->scan($order)->assertRedirect(route('delivery.dashboard'))
                ->assertSessionHas('error', fn ($m) => str_contains($m, $phrase));
        }
    }

    public function test_scan_by_another_rider_still_says_not_assigned_without_leaking_status(): void
    {
        $order = $this->order('out_for_delivery', 'assigned');
        $other = User::factory()->create(['role' => 'delivery']);

        $this->scan($order, $other)->assertSessionHas('error', 'This order is not assigned to you!');
    }

    public function test_scan_for_a_booking_is_unchanged(): void
    {
        $booking = $this->order('confirmed', 'assigned', ['event_date' => now()->addDays(5), 'latitude' => null, 'longitude' => null]);

        $this->scan($booking)->assertRedirect(route('delivery.orders.show', $booking->delivery->id));
    }
}
