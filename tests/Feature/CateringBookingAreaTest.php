<?php

namespace Tests\Feature;

use App\Models\CateringPackage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Exercises the real catering Avail flow (storeBooking -> checkout -> store)
// with tampered-POST-style requests directly against the route, the same
// way DataValidationTest does for the regular menu checkout — proving the
// server never trusts the dropdown the browser rendered.
class CateringBookingAreaTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function package(): CateringPackage
    {
        return CateringPackage::create([
            'name' => 'Test Package', 'pax' => 20, 'price' => 2000, 'is_available' => true,
        ]);
    }

    private function bookingPayload(CateringPackage $package, array $overrides = []): array
    {
        return array_merge([
            'package_id' => $package->id,
            'contact_name' => 'Juan Cruz',
            'contact_number' => '09171234567',
            'province' => 'Oriental Mindoro',
            'municipality' => 'Calapan City',
            'barangay' => 'Balingayan',
            'street' => '123 Test St',
            'event_date' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_booking_accepts_oriental_mindoro(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $response = $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('customer.packages.checkout'));
    }

    public function test_booking_accepts_occidental_mindoro_outside_san_jose(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $response = $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package, [
            'province' => 'Occidental Mindoro',
            'municipality' => 'Mamburao',
            'barangay' => 'Poblacion 1',
        ]));

        $response->assertSessionHasNoErrors();
    }

    public function test_booking_rejects_lubang_municipality(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $response = $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package, [
            'province' => 'Occidental Mindoro',
            'municipality' => 'Lubang',
            'barangay' => 'Tilik',
        ]));

        $response->assertSessionHasErrors('barangay');
    }

    public function test_booking_rejects_looc_municipality(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $response = $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package, [
            'province' => 'Occidental Mindoro',
            'municipality' => 'Looc',
            'barangay' => 'Agkawayan',
        ]));

        $response->assertSessionHasErrors('barangay');
    }

    public function test_booking_rejects_ilin_island_even_though_san_jose_is_otherwise_eligible(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $response = $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package, [
            'province' => 'Occidental Mindoro',
            'municipality' => 'San Jose',
            'barangay' => 'Catayungan',
        ]));

        $response->assertSessionHasErrors('barangay');
    }

    public function test_booking_rejects_a_province_outside_mindoro(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $response = $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package, [
            'province' => 'Batangas',
            'municipality' => 'Batangas City',
            'barangay' => 'Poblacion',
        ]));

        $response->assertSessionHasErrors('barangay');
    }

    public function test_full_booking_flow_creates_an_order_with_structured_address(): void
    {
        $user = $this->customer();
        $package = $this->package();

        $this->actingAs($user)->post('/customer/packages/avail', $this->bookingPayload($package));
        $response = $this->actingAs($user)->post('/customer/packages/checkout', [
            'payment_method' => 'cash_on_delivery',
        ]);

        $order = Order::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($order, 'Order was not created — response: ' . $response->status());
        $this->assertSame('Oriental Mindoro', $order->province);
        $this->assertSame('Calapan City', $order->municipality);
        $this->assertSame('Balingayan', $order->barangay);
        $this->assertStringContainsString('Barangay Balingayan', $order->delivery_address);
        $this->assertStringContainsString('Calapan City', $order->delivery_address);
    }
}
