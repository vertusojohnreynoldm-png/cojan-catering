<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St, San Jose',
            'delivery_phone'   => '09111111111',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('delivery_phone');
    }

    public function test_checkout_rejects_a_landline_number(): void
    {
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St, San Jose',
            'delivery_phone'   => '028123456',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('delivery_phone');
    }

    public function test_checkout_rejects_a_blank_address(): void
    {
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '   ',
            'delivery_phone'   => '09171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasErrors('delivery_address');
    }

    public function test_checkout_normalizes_a_plus63_phone_number_to_the_stored_format(): void
    {
        $user = $this->customerWithCart();

        $response = $this->actingAs($user)->post('/customer/orders', [
            'delivery_address' => '123 Test St, San Jose',
            'delivery_phone'   => '+639171234567',
            'payment_method'   => 'cash_on_delivery',
            'delivery_type'    => 'asap',
        ]);

        $response->assertSessionHasNoErrors();
        $order = Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertSame('09171234567', $order->delivery_phone);
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
