<?php

namespace Tests\Feature;

use App\Mail\BookingPaymentMail;
use App\Models\ActivityLog;
use App\Models\CateringPackage;
use App\Models\Order;
use App\Models\User;
use App\Models\Utensil;
use App\Services\BookingSettings;
use App\Services\UnpaidBookingExpirer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// Stage 4: GCash downpayment for catering bookings — amount + deadline,
// receipt upload, admin confirm/reject, and automatic expiry.
class DownpaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
    }

    // ---- helpers ----------------------------------------------------------

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
    }

    private function package(float $price = 2000): CateringPackage
    {
        return CateringPackage::create(['name' => 'Fiesta', 'pax' => 20, 'price' => $price, 'is_available' => true]);
    }

    private function book(User $customer, ?CateringPackage $package = null, string $eventDate = '2026-11-20 15:00'): Order
    {
        $package ??= $this->package();

        $this->actingAs($customer)->post('/customer/packages/avail', [
            'package_id' => $package->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'event_date' => $eventDate,
        ])->assertSessionHasNoErrors();
        $this->actingAs($customer)->post('/customer/packages/checkout', []);

        return Order::bookings()->where('user_id', $customer->id)->latest('id')->firstOrFail();
    }

    private function receipt(string $name = 'receipt.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 1200);
    }

    // ---- booking creates a Pending Payment downpayment -----------------------

    public function test_new_booking_is_pending_payment_with_downpayment_and_deadline(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);

        $this->assertSame('gcash', $booking->payment_method);
        $this->assertEquals(1000.00, (float) $booking->downpayment_amount);
        $this->assertEquals(1000.00, $booking->balanceAmount());
        $this->assertTrue($booking->payment_due_at->equalTo(now()->addHours(24)));
        $this->assertTrue($booking->isAwaitingDownpayment());
        $this->assertSame('Pending Payment', $booking->statusLabel());
        $this->assertSame('pending', $booking->status, 'The status enum itself is untouched.');

        Mail::assertSent(BookingPaymentMail::class, fn ($m) => $m->type === 'received' && $m->hasTo($customer->email));
    }

    public function test_downpayment_percent_and_deadline_follow_the_admin_settings(): void
    {
        BookingSettings::save(['catering_downpayment_percent' => 30, 'payment_deadline_hours' => 6]);

        $booking = $this->book($this->customer());

        $this->assertEquals(600.00, (float) $booking->downpayment_amount);
        $this->assertTrue($booking->payment_due_at->equalTo(now()->addHours(6)));
    }

    public function test_deadline_never_falls_after_the_event_start(): void
    {
        BookingSettings::save(['catering_min_lead_days' => 0, 'payment_deadline_hours' => 48]);

        $booking = $this->book($this->customer(), null, '2026-11-05 08:00'); // 22 hours away

        $this->assertSame('2026-11-05 08:00:00', $booking->payment_due_at->format('Y-m-d H:i:s'));
    }

    public function test_checkout_and_booking_pages_show_downpayment_balance_and_deadline(): void
    {
        $customer = $this->customer();
        $package = $this->package(2500);

        $this->actingAs($customer)->post('/customer/packages/avail', [
            'package_id' => $package->id, 'contact_name' => 'Juan Cruz', 'contact_number' => '09171234567',
            'province' => 'Occidental Mindoro', 'municipality' => 'San Jose', 'barangay' => 'Caminawit',
            'street' => '123 Test St', 'event_date' => '2026-11-20 15:00',
        ]);
        $this->actingAs($customer)->get('/customer/packages/checkout')
            ->assertOk()
            ->assertSee('Downpayment (50%)')
            ->assertSee('₱1,250.00')
            ->assertSee('Balance on the event day')
            ->assertSee('Pay by Thu, Nov 05, 2026 10:00 AM')
            ->assertDontSee('Cash on Delivery');

        $this->actingAs($customer)->post('/customer/packages/checkout', []);
        $booking = Order::bookings()->firstOrFail();

        $this->actingAs($customer)->get("/customer/orders/{$booking->id}")
            ->assertOk()
            ->assertSee('Pending Payment')
            ->assertSee('Upload Receipt')
            ->assertSee('₱1,250.00')
            ->assertSee('Downpayment due');
    }

    // ---- receipt upload ------------------------------------------------------

    public function test_customer_uploads_a_receipt_and_it_goes_under_review(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);

        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", [
            'receipt' => $this->receipt(), 'gcash_reference' => '1234 567 890',
        ])->assertRedirect(route('customer.orders.show', $booking->id));

        $booking->refresh();
        $this->assertMatchesRegularExpression('#^payment-receipts/[0-9a-f-]{36}\.jpg$#', $booking->receipt_path);
        Storage::disk('public')->assertExists($booking->receipt_path);
        $this->assertSame('1234567890', $booking->gcash_reference);
        $this->assertTrue($booking->hasReceiptUnderReview());
        $this->assertSame('Payment Under Review', $booking->statusLabel());
        $this->assertTrue(ActivityLog::where('action', 'booking.receipt_uploaded')->exists());
    }

    public function test_receipt_upload_answers_json_for_the_compressing_uploader(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);

        $this->actingAs($customer)
            ->postJson("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()])
            ->assertOk()
            ->assertJson(['redirect' => route('customer.orders.show', $booking->id)]);
    }

    public function test_receipt_must_be_an_image_and_only_the_owner_can_upload(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);

        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", [
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('receipt');

        $this->actingAs($this->customer())->post("/customer/orders/{$booking->id}/receipt", [
            'receipt' => $this->receipt(),
        ])->assertNotFound();

        $this->assertNull($booking->fresh()->receipt_path);
    }

    public function test_re_upload_replaces_the_old_file(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);

        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()]);
        $first = $booking->fresh()->receipt_path;
        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt('again.png')]);
        $second = $booking->fresh()->receipt_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_no_upload_once_paid_or_past_the_deadline(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        $booking->update(['downpayment_paid_at' => now()]);

        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()])
            ->assertSessionHas('error');

        $late = $this->book($this->customer(), null, '2026-11-21 15:00');
        $this->travel(25)->hours();
        $this->actingAs($late->user)->post("/customer/orders/{$late->id}/receipt", ['receipt' => $this->receipt()])
            ->assertSessionHas('error');
    }

    public function test_receipt_is_served_only_to_its_owner_and_admins(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()]);

        $this->actingAs($customer)->get("/orders/{$booking->id}/receipt")->assertOk();
        $this->actingAs($this->admin())->get("/orders/{$booking->id}/receipt")->assertOk();
        $this->actingAs($this->customer())->get("/orders/{$booking->id}/receipt")->assertForbidden();

        auth()->logout();
        $this->get("/orders/{$booking->id}/receipt")->assertRedirect(route('login'));
    }

    // ---- admin confirm / reject ----------------------------------------------

    public function test_admin_confirms_the_downpayment_then_the_balance(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/mark-paid")
            ->assertSessionHas('success', 'Downpayment confirmed. Balance of ₱1,000.00 is due on the event day.');

        $booking->refresh();
        $this->assertNotNull($booking->downpayment_paid_at);
        $this->assertSame('unpaid', $booking->payment_status, 'The balance is still due.');
        $this->assertFalse($booking->isAwaitingDownpayment());
        $this->assertSame('Pending', $booking->statusLabel());
        Mail::assertSent(BookingPaymentMail::class, fn ($m) => $m->type === 'confirmed');
        $this->assertTrue(ActivityLog::where('action', 'booking.downpayment_confirmed')->exists());

        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/mark-paid");
        $this->assertSame('paid', $booking->fresh()->payment_status);
    }

    public function test_admin_rejects_a_receipt_and_the_customer_can_upload_again(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/reject-receipt", ['reason' => ''])
            ->assertSessionHasErrors('reason');

        // Review took a while — past the original deadline.
        $this->travel(30)->hours();
        $this->actingAs($admin)->post("/admin/orders/{$booking->id}/reject-receipt", ['reason' => 'Amount is ₱500, expected ₱1,000'])
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertNotNull($booking->receipt_rejected_at);
        $this->assertSame('Amount is ₱500, expected ₱1,000', $booking->receipt_rejection_reason);
        $this->assertFalse($booking->hasReceiptUnderReview());
        $this->assertTrue($booking->payment_due_at->isFuture(), 'A fresh window to re-upload.');
        Mail::assertSent(BookingPaymentMail::class, fn ($m) => $m->type === 'rejected');
        $this->assertTrue(ActivityLog::where('action', 'booking.receipt_rejected')->exists());

        $this->actingAs($customer)->get("/customer/orders/{$booking->id}")->assertSee('Amount is ₱500, expected ₱1,000');
        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()]);
        $this->assertTrue($booking->fresh()->hasReceiptUnderReview());
    }

    public function test_admin_page_shows_receipt_and_hides_confirm_booking_until_paid(): void
    {
        $customer = $this->customer();
        $booking = $this->book($customer);
        $this->actingAs($customer)->post("/customer/orders/{$booking->id}/receipt", ['receipt' => $this->receipt()]);

        $this->actingAs($this->admin())->get("/admin/orders/{$booking->id}")
            ->assertOk()
            ->assertSee('Payment Under Review')
            ->assertSee('Confirm Downpayment')
            ->assertSee('Reject Receipt')
            ->assertSee(route('orders.receipt', $booking->id), false)
            ->assertDontSee('✓ Confirm Booking');
    }

    // ---- auto-expiry ---------------------------------------------------------

    public function test_unpaid_booking_expires_after_the_deadline(): void
    {
        $chairs = Utensil::create(['name' => 'Chairs', 'quantity' => 50]);
        $package = $this->package();
        $package->utensils()->attach($chairs->id, ['quantity_needed' => 10]);
        $customer = $this->customer();
        $booking = $this->book($customer, $package);
        $this->assertSame(40, $chairs->fresh()->quantity);

        $this->travel(23)->hours();
        $this->assertSame(0, UnpaidBookingExpirer::run(), 'Not before the deadline.');

        $this->travel(2)->hours();
        $this->artisan('bookings:expire-unpaid')->expectsOutput('Expired 1 unpaid booking.')->assertSuccessful();

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);
        $this->assertSame(UnpaidBookingExpirer::REASON, $booking->cancellation_reason);
        $this->assertSame(50, $chairs->fresh()->quantity, 'Stage 3 returns the utensils.');
        Mail::assertSent(BookingPaymentMail::class, fn ($m) => $m->type === 'expired' && $m->hasTo($customer->email));
        $this->assertTrue(ActivityLog::where('action', 'booking.expired_unpaid')->exists());

        $this->actingAs($customer)->get("/customer/orders/{$booking->id}")
            ->assertSee("cancelled because the downpayment wasn't received", false);
    }

    public function test_receipt_under_review_never_expires_but_a_rejected_one_does(): void
    {
        $reviewing = $this->book($this->customer());
        $this->actingAs($reviewing->user)->post("/customer/orders/{$reviewing->id}/receipt", ['receipt' => $this->receipt()]);

        $rejected = $this->book($this->customer(), null, '2026-11-21 15:00');
        $rejected->update(['receipt_path' => 'payment-receipts/x.jpg', 'receipt_uploaded_at' => now(), 'receipt_rejected_at' => now()]);

        $paid = $this->book($this->customer(), null, '2026-11-22 15:00');
        $paid->update(['downpayment_paid_at' => now()]);

        $this->travel(48)->hours();
        UnpaidBookingExpirer::run();

        $this->assertSame('pending', $reviewing->fresh()->status);
        $this->assertSame('cancelled', $rejected->fresh()->status);
        $this->assertSame('pending', $paid->fresh()->status);
    }

    public function test_menu_orders_and_old_bookings_without_a_deadline_never_expire(): void
    {
        $menu = Order::create([
            'user_id' => $this->customer()->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'pending', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'unpaid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567',
        ]);
        $old = $this->book($this->customer());
        $old->update(['payment_due_at' => null, 'downpayment_amount' => null]); // placed before Stage 4

        $this->travel(72)->hours();
        UnpaidBookingExpirer::run();

        $this->assertSame('pending', $menu->fresh()->status);
        $this->assertSame('pending', $old->fresh()->status);
    }

    public function test_lazy_expiry_runs_from_busy_pages_at_most_every_five_minutes(): void
    {
        $admin = $this->admin();
        $first = $this->book($this->customer());
        $second = $this->book($this->customer(), null, '2026-11-21 15:00');
        $second->update(['payment_due_at' => now()->addHours(25)->addMinutes(2)]);

        $this->travel(25)->hours();
        $this->actingAs($admin)->get('/admin/bookings')->assertOk();
        $this->assertSame('cancelled', $first->fresh()->status);

        $this->travel(3)->minutes(); // second is now overdue too...
        $this->actingAs($admin)->get('/admin/bookings');
        $this->assertSame('pending', $second->fresh()->status, '...but the check is throttled.');

        $this->travel(3)->minutes();
        $this->actingAs($second->user)->get('/customer/orders');
        $this->assertSame('cancelled', $second->fresh()->status);
    }

    public function test_reopening_an_expired_booking_gives_a_fresh_deadline(): void
    {
        $booking = $this->book($this->customer());
        $this->travel(25)->hours();
        UnpaidBookingExpirer::run();

        $this->actingAs($this->admin())->post("/admin/orders/{$booking->id}/status", ['status' => 'pending']);

        $booking->refresh();
        $this->assertSame('pending', $booking->status);
        $this->assertTrue($booking->payment_due_at->isFuture());
        $this->assertTrue($booking->isAwaitingDownpayment());
    }

    // ---- emails actually render ---------------------------------------------

    public function test_every_booking_payment_email_renders(): void
    {
        // Mail::fake() never renders the template, so a Blade error would
        // only show up in production — render each variant for real here.
        $booking = $this->book($this->customer());
        $booking->update(['receipt_rejection_reason' => 'Amount is wrong', 'receipt_rejected_at' => now()]);

        $expected = [
            'received'  => 'Pay by',
            'confirmed' => 'your date is secured',
            'rejected'  => 'Reason: <strong>Amount is wrong</strong>',
            'expired'   => 'so the booking was cancelled',
        ];

        foreach ($expected as $type => $text) {
            if ($type === 'confirmed') {
                $booking->downpayment_paid_at = now();
            }
            $html = (new BookingPaymentMail($booking, $type))->render();
            $this->assertStringContainsString('₱1,000.00', html_entity_decode($html), "$type: amounts");
            if ($type !== 'received') {
                $this->assertStringContainsString($text, $html, "$type email");
            }
        }

        $status = (new \App\Mail\OrderStatusMail($booking))->render();
        $this->assertStringContainsString('Balance on the event day', $status);
    }

    // ---- chat edge case -------------------------------------------------------

    public function test_customer_chat_works_when_no_admin_account_exists(): void
    {
        $this->actingAs($this->customer())->getJson('/customer/chat/messages')
            ->assertOk()
            ->assertJson(['messages' => [], 'admin_id' => null, 'unavailable' => true]);
    }
}
