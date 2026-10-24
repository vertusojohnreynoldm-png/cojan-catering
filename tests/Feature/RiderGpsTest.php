<?php

namespace Tests\Feature;

use App\Events\DeliveryLocationUpdated;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage C: one GPS watcher on the rider page, heading/speed, and the
// server's stop flag that ends sharing when a delivery is over.
class RiderGpsTest extends TestCase
{
    use RefreshDatabase;

    private function rider(): User
    {
        return User::factory()->create(['role' => 'delivery', 'email_verified_at' => now()]);
    }

    private function delivery(User $rider, string $status = 'picked_up', array $order = []): Delivery
    {
        $o = Order::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'customer'])->id,
            'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'out_for_delivery', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Mabini',
            'latitude' => 12.3697, 'longitude' => 121.0952,
        ], $order));

        return Delivery::create(['order_id' => $o->id, 'user_id' => $rider->id, 'status' => $status, 'assigned_at' => now()]);
    }

    private function ping(array $overrides = []): array
    {
        return array_merge(['lat' => 12.36, 'lng' => 121.08, 'accuracy' => 12, 'heading' => 87.4, 'speed' => 6.2], $overrides);
    }

    // ---- location endpoint ------------------------------------------------------

    public function test_location_update_stores_heading_and_speed_and_broadcasts(): void
    {
        Event::fake([DeliveryLocationUpdated::class]);
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping())
            ->assertOk()
            ->assertExactJson(['success' => true, 'stop' => false]);

        $d->refresh();
        $this->assertEqualsWithDelta(87.4, (float) $d->current_heading, 0.01);
        $this->assertEqualsWithDelta(6.2, (float) $d->current_speed, 0.01);
        $this->assertNotNull($d->last_location_update);
        Event::assertDispatched(DeliveryLocationUpdated::class);
    }

    public function test_broadcast_payload_includes_heading_and_speed(): void
    {
        $d = $this->delivery($this->rider());
        $d->update(['current_lat' => 12.36, 'current_lng' => 121.08, 'current_accuracy' => 10, 'current_heading' => 270, 'current_speed' => 5.5, 'last_location_update' => now()]);

        $payload = (new DeliveryLocationUpdated($d->fresh()))->broadcastWith();

        $this->assertSame(270.0, $payload['heading']);
        $this->assertSame(5.5, $payload['speed']);
    }

    public function test_heading_and_speed_are_optional(): void
    {
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping(['heading' => null, 'speed' => null]))->assertOk();
        $this->assertNull($d->fresh()->current_heading);
    }

    public function test_server_tells_the_page_to_stop_when_the_delivery_is_over(): void
    {
        $rider = $this->rider();

        foreach (['delivered', 'failed'] as $status) {
            $d = $this->delivery($rider, $status);
            $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping())
                ->assertStatus(409)
                ->assertJson(['stop' => true, 'message' => 'This delivery is finished — location sharing has stopped.']);
            $this->assertNull($d->fresh()->current_lat, 'Nothing is stored after the delivery ends.');
        }

        $cancelled = $this->delivery($rider, 'in_transit', ['status' => 'cancelled']);
        $this->actingAs($rider)->postJson("/delivery/orders/{$cancelled->id}/location", $this->ping())
            ->assertStatus(409)
            ->assertJson(['stop' => true, 'message' => 'This order was cancelled — location sharing has stopped.']);
    }

    public function test_bookings_never_get_live_tracking(): void
    {
        $rider = $this->rider();
        $booking = $this->delivery($rider, 'picked_up', ['event_date' => now()->addDays(5)]);

        $this->actingAs($rider)->postJson("/delivery/orders/{$booking->id}/location", $this->ping())
            ->assertStatus(409)->assertJson(['stop' => true]);
    }

    public function test_assigned_but_not_picked_up_is_not_shared_yet_but_not_stopped(): void
    {
        $rider = $this->rider();
        $d = $this->delivery($rider, 'assigned');

        $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping())
            ->assertStatus(422)
            ->assertJson(['stop' => false, 'message' => 'Location sharing starts once you mark the order Picked Up.']);
    }

    public function test_bad_readings_are_rejected(): void
    {
        $rider = $this->rider();
        $d = $this->delivery($rider);

        $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping(['accuracy' => 8000]))->assertStatus(422);
        $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping(['heading' => 400]))->assertStatus(422);
        $this->actingAs($rider)->postJson("/delivery/orders/{$d->id}/location", $this->ping(['speed' => -1]))->assertStatus(422);
    }

    public function test_only_the_assigned_rider_can_share(): void
    {
        $d = $this->delivery($this->rider());

        $this->actingAs($this->rider())->postJson("/delivery/orders/{$d->id}/location", $this->ping())->assertNotFound();
    }

    // ---- rider page ----------------------------------------------------------------

    public function test_rider_page_has_exactly_one_gps_watcher_and_the_right_sharing_flag(): void
    {
        $rider = $this->rider();

        $picked = $this->actingAs($rider)->get('/delivery/orders/' . $this->delivery($rider, 'picked_up')->id)->assertOk()->getContent();
        $this->assertSame(1, substr_count($picked, 'watchPosition('));
        $this->assertStringContainsString('const SHARING = true;', $picked);
        $this->assertStringContainsString('id="share-pill"', $picked);
        $this->assertStringNotContainsString('id="location-status"', $picked); // the old second watcher is gone

        $assigned = $this->actingAs($rider)->get('/delivery/orders/' . $this->delivery($rider, 'assigned')->id)->getContent();
        $this->assertSame(1, substr_count($assigned, 'watchPosition('));
        $this->assertStringContainsString('const SHARING = false;', $assigned);
    }

    public function test_finished_or_booking_pages_have_no_gps_watcher(): void
    {
        $rider = $this->rider();

        foreach ([$this->delivery($rider, 'delivered'), $this->delivery($rider, 'picked_up', ['event_date' => now()->addDays(5)])] as $d) {
            $this->actingAs($rider)->get("/delivery/orders/{$d->id}")
                ->assertOk()
                ->assertDontSee('watchPosition(', false)
                ->assertDontSee('wakeLock', false);
        }
    }
}
