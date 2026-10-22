<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\DeliverySlots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// Exercises the real checkout flow with time frozen inside business hours —
// written specifically because the live dev site is legitimately closed for
// ordering at the moment this was built (~midnight Asia/Manila), and the
// business-hours gate itself must not be weakened just to make testing
// easier. RefreshDatabase runs this against an isolated in-memory SQLite DB
// (see phpunit.xml), never the real dev database.
class DeliveryTimeFrameTest extends TestCase
{
    use RefreshDatabase;

    private function freezeToOpenHours(): Carbon
    {
        // A Wednesday, 10:00 AM Asia/Manila — comfortably inside 7:30-20:00
        // and far enough from midnight that "+1 day" slot generation in the
        // other tests doesn't brush up against month/day boundaries.
        $now = Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila');
        Carbon::setTestNow($now);

        return $now;
    }

    private function customerWithCart(): User
    {
        $category = Category::create(['name' => 'Mains']);
        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Test Dish',
            'description' => 'A dish',
            'price' => 150,
            'is_available' => true,
        ]);

        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);

        session(['cart' => [
            $item->id => ['id' => $item->id, 'name' => $item->name, 'price' => 150, 'quantity' => 1, 'type' => 'menu'],
        ]]);

        return $user;
    }

    public function test_checkout_page_shows_delivery_time_ui_during_business_hours(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->get('/customer/checkout');

        $response->assertOk();
        $response->assertSee('Delivery Time', false);
        $response->assertSee('dt-asap', false);
        $response->assertSee('dt-scheduled', false);
        $response->assertSee('slot-chips', false);
    }

    public function test_delivery_slots_endpoint_returns_real_slots(): void
    {
        $now = $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->getJson(
            '/customer/checkout/delivery-slots?date=' . $now->toDateString()
        );

        $response->assertOk();
        $slots = $response->json('slots');
        $this->assertNotEmpty($slots);
        // 10:00 AM + 45min lead time means the 10:00-10:30 slot itself should
        // already be excluded, but something later in the day must exist.
        $this->assertNotContains('10:00 AM - 10:30 AM', array_column($slots, 'label'));
    }

    public function test_scheduled_order_is_stored_with_correct_fields(): void
    {
        $now = $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $slots = DeliverySlots::slotsFor($now->toDateString());
        $this->assertNotEmpty($slots, 'Precondition: there must be a valid slot to pick in this test.');
        $chosenSlot = $slots[0];

        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'scheduled',
            'delivery_date'    => $now->toDateString(),
            'delivery_slot'    => $chosenSlot['value'],
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order, 'Order was not created — response: ' . $response->status());
        $response->assertRedirect(route('customer.orders.show', $order->id));

        $this->assertSame('scheduled', $order->delivery_type);
        $this->assertSame($chosenSlot['label'], $order->delivery_window_label);
        $this->assertNotNull($order->requested_delivery_at);
        $this->assertSame('Today, ' . $chosenSlot['label'], $order->deliveryTimeLabel());
    }

    public function test_asap_order_is_stored_with_null_scheduled_fields(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $order = Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('asap', $order->delivery_type);
        $this->assertNull($order->requested_delivery_at);
        $this->assertSame('ASAP', $order->deliveryTimeLabel());
    }

    public function test_server_rejects_a_slot_that_is_not_actually_valid(): void
    {
        $now = $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        // A slot inside business hours on paper, but not one DeliverySlots
        // would ever generate (wrong 5-minute alignment) — simulates a
        // tampered client request bypassing the chip UI entirely.
        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'scheduled',
            'delivery_date'    => $now->toDateString(),
            'delivery_slot'    => $now->toDateString() . ' 11:17',
        ]);

        $response->assertSessionHasErrors('delivery_slot');
        $this->assertNull(Order::latest('id')->first());
    }

    public function test_asap_orders_still_respect_business_hours_closed(): void
    {
        // Midnight — outside 7:30-20:00 — ASAP must not bypass the existing
        // BusinessHours gate just because a time frame was chosen.
        Carbon::setTestNow(Carbon::parse('2026-11-04 00:30:00', 'Asia/Manila'));
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertRedirect(route('customer.cart'));
        $this->assertNull(Order::latest('id')->first());
    }
}
