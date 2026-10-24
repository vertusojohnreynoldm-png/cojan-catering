<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

// Mobile pass: the wide customer/rider tables render as labelled cards on
// phones (cj-table-stack + data-label). Layout itself is checked with
// Playwright screenshots; these guard the markup the CSS relies on.
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function order(User $customer, array $attributes = []): Order
    {
        $item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains'])->id,
            'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true,
        ]);
        $order = Order::create(array_merge([
            'user_id' => $customer->id,
            'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'out_for_delivery', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Mabini',
        ], $attributes));
        OrderItem::create(['order_id' => $order->id, 'menu_item_id' => $item->id, 'quantity' => 1, 'unit_price' => 150, 'subtotal' => 150]);

        return $order;
    }

    public function test_cart_renders_as_stacked_cards_with_labels(): void
    {
        $item = MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains'])->id,
            'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true, 'image' => 'menu/adobo.jpg',
        ]);
        $this->actingAs($this->customer());
        session(['cart' => ['menu_' . $item->id => [
            'type' => 'menu', 'id' => $item->id, 'name' => 'Adobo', 'price' => 150, 'quantity' => 2, 'image' => 'menu/adobo.jpg',
        ]]]);

        $this->get('/customer/cart')->assertOk()
            ->assertSee('cj-table cj-table-stack', false)
            ->assertSee('class="cj-td-thumb"', false)
            ->assertSee('data-label="Price"', false)
            ->assertSee('data-label="Qty"', false)
            ->assertSee('data-label="Subtotal"', false)
            ->assertSee('class="cj-remove-link"', false)
            ->assertSee('aria-label="Remove Adobo"', false);
    }

    public function test_orders_list_and_order_detail_render_as_stacked_cards(): void
    {
        $customer = $this->customer();
        $order = $this->order($customer);

        $this->actingAs($customer)->get('/customer/orders')->assertOk()
            ->assertSee('cj-table cj-table-stack', false)
            ->assertSee('data-label="Status"', false)
            ->assertSee('data-label="Payment"', false)
            ->assertSee($order->order_number);

        $this->actingAs($customer)->get('/customer/orders/' . $order->id)->assertOk()
            ->assertSee('cj-table cj-table-stack', false)
            ->assertSee('data-label="Subtotal"', false);
    }

    public function test_rider_dashboard_and_detail_render_as_stacked_cards(): void
    {
        $rider = User::factory()->create(['role' => 'delivery', 'email_verified_at' => now()]);
        $delivery = Delivery::create([
            'order_id' => $this->order($this->customer())->id, 'user_id' => $rider->id,
            'status' => 'delivered', 'assigned_at' => now(), 'delivered_at' => now(),
        ]);

        $this->actingAs($rider)->get('/delivery/dashboard')->assertOk()
            ->assertSee('cj-table cj-table-stack', false)
            ->assertSee('data-label="Customer"', false)
            ->assertSee('data-label="Address"', false);

        // An older delivery with no proof says so instead of showing an empty card.
        $this->actingAs($rider)->get('/delivery/orders/' . $delivery->id)->assertOk()
            ->assertSee('cj-table cj-table-stack', false)
            ->assertSee('No proof photo or note was recorded for this delivery.');
    }
}
