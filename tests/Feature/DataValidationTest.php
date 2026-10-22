<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

// Confirms the Stage 1 validation rules (PhilippineMobileNumber,
// ValidPersonName) and the price/required tightening on admin forms are
// actually wired into the real endpoints — not just unit-tested in
// isolation. Each test posts directly to the route, the same way a
// tampered/non-JS request would, so passing here means the server never
// trusts the browser.
class DataValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_a_placeholder_name(): void
    {
        $response = $this->post('/register', [
            'name' => 'asdf',
            'email' => 'newcustomer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertGuest();
    }

    // "Accepts a valid name with a middle initial" is covered at the unit
    // level (ValidPersonNameTest) — not duplicated here as a feature test
    // because /register's email:rfc,dns rule needs real DNS resolution,
    // which this sandboxed test environment doesn't have (the pre-existing
    // Breeze RegistrationTest::test_new_users_can_register fails the same
    // way, for the same reason, independent of this change).

    // Every /customer/orders POST in this file passes through the
    // menu-ordering BusinessHours gate before reaching the validation this
    // file actually tests, regardless of whether the request is otherwise
    // expected to fail earlier — freezing to a known-open moment (same
    // approach as DeliveryTimeFrameTest) keeps these tests from depending
    // on whatever the real wall-clock happens to be when they run.
    private function freezeToOpenHours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
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

    public function test_checkout_rejects_a_junk_repeated_digit_phone_number(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'San Jose',
            'barangay'         => 'Caminawit',
            'street'           => '123 Test St',
            'delivery_phone'   => '09111111111',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('delivery_phone');
    }

    public function test_checkout_rejects_a_landline_number(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'San Jose',
            'barangay'         => 'Caminawit',
            'street'           => '123 Test St',
            'delivery_phone'   => '028123456',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('delivery_phone');
    }

    public function test_checkout_rejects_a_blank_street(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'San Jose',
            'barangay'         => 'Caminawit',
            'street'           => '   ',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('street');
    }

    public function test_checkout_normalizes_a_plus63_phone_number_to_the_stored_format(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'San Jose',
            'barangay'         => 'Caminawit',
            'street'           => '123 Test St',
            'delivery_phone'   => '+639171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasNoErrors();
        $order = Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('09171234567', $order->delivery_phone);
    }

    public function test_checkout_rejects_a_boat_only_san_jose_barangay(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'San Jose',
            'barangay'         => 'Ansiray', // Ilin Island — boat access only
            'street'           => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('barangay');
        $this->assertNull(Order::where('user_id', $user->id)->first());
    }

    public function test_checkout_rejects_a_tampered_municipality_outside_san_jose(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithCart();

        // A real barangay, but in the wrong municipality — simulates a
        // tampered POST that never went through the (locked) province/
        // municipality hidden inputs the browser would normally send.
        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'Sablayan',
            'barangay'         => 'Poblacion',
            'street'           => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('barangay');
        $this->assertNull(Order::where('user_id', $user->id)->first());
    }

    private function customerWithMixedCart(): User
    {
        $category = Category::create(['name' => 'Mains']);
        $menuItem = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Test Dish',
            'description' => 'A dish',
            'price' => 150,
            'is_available' => true,
        ]);
        $package = \App\Models\CateringPackage::create([
            'name' => 'Test Package', 'pax' => 20, 'price' => 2000, 'is_available' => true,
        ]);

        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);

        session(['cart' => [
            $menuItem->id => ['id' => $menuItem->id, 'name' => $menuItem->name, 'price' => 150, 'quantity' => 1, 'type' => 'menu'],
            'package_' . $package->id => ['id' => $package->id, 'name' => $package->name, 'price' => 2000, 'quantity' => 1, 'type' => 'package'],
        ]]);

        return $user;
    }

    public function test_mixed_cart_with_a_menu_item_is_held_to_the_san_jose_only_rule(): void
    {
        $this->freezeToOpenHours();
        $user = $this->customerWithMixedCart();

        // A perfectly valid CATERING area (Mamburao, Occidental Mindoro),
        // but the cart has a menu item in it, so the stricter San-Jose-only
        // rule must still apply to the whole order — per the approved
        // mixed-cart decision.
        $response = $this->actingAs($user)->post('/customer/orders', [
            'province'         => 'Occidental Mindoro',
            'municipality'     => 'Mamburao',
            'barangay'         => 'Poblacion 1',
            'street'           => '123 Test St',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('barangay');
        $this->assertNull(Order::where('user_id', $user->id)->first());
    }

    public function test_admin_cannot_create_a_menu_item_with_zero_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Mains']);

        $response = $this->actingAs($admin)->post('/admin/menu', [
            'name' => 'Free Item',
            'category_id' => $category->id,
            'price' => 0,
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_admin_cannot_create_a_user_with_an_invalid_phone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Staff Member',
            'email' => 'staff@example.com',
            'password' => 'password123',
            'role' => 'delivery',
            'phone' => '12345',
        ]);

        $response->assertSessionHasErrors('phone');
    }
}
