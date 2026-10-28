<?php

namespace Tests\Feature;

use App\Mail\OrderStatusMail;
use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\Delivery;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\BookingSettings;
use App\Services\Ratings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// Ratings, Stage 1: who can rate, one rating per order, edit and rating
// windows, spam safety, and the photo route.
class RatingTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $adobo;
    private MenuItem $sinigang;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));
        Storage::fake('public');

        $category = Category::create(['name' => 'Mains']);
        $this->adobo = MenuItem::create(['category_id' => $category->id, 'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true]);
        $this->sinigang = MenuItem::create(['category_id' => $category->id, 'name' => 'Sinigang', 'description' => 'x', 'price' => 180, 'is_available' => true]);
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => 'customer', 'email_verified_at' => now()], $attributes));
    }

    private function rider(): User
    {
        return User::factory()->create(['role' => 'delivery', 'email_verified_at' => now(), 'name' => 'Juan Rider']);
    }

    /** A delivered menu order with two lines, delivered by $rider (if given). */
    private function menuOrder(User $customer, string $status = 'delivered', ?User $rider = null): Order
    {
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => $status, 'payment_method' => 'cash_on_delivery', 'payment_status' => 'paid',
            'subtotal' => 330, 'delivery_fee' => 50, 'total_amount' => 380,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'barangay' => 'Mabini',
        ]);
        OrderItem::create(['order_id' => $order->id, 'menu_item_id' => $this->adobo->id, 'quantity' => 2, 'unit_price' => 150, 'subtotal' => 300]);
        OrderItem::create(['order_id' => $order->id, 'menu_item_id' => $this->sinigang->id, 'quantity' => 1, 'unit_price' => 180, 'subtotal' => 180]);
        if ($rider) {
            Delivery::create(['order_id' => $order->id, 'user_id' => $rider->id, 'status' => 'delivered', 'assigned_at' => now(), 'delivered_at' => now()]);
        }

        return $order->fresh();
    }

    private function bookingOrder(User $customer): Order
    {
        $package = CateringPackage::create(['name' => 'Fiesta', 'pax' => 50, 'price' => 15000, 'is_available' => true]);
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'delivered', 'payment_method' => 'gcash', 'payment_status' => 'paid',
            'subtotal' => 15000, 'delivery_fee' => 0, 'total_amount' => 15000,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567', 'event_date' => now()->subDay(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'package_id' => $package->id, 'quantity' => 1, 'unit_price' => 15000, 'subtotal' => 15000]);

        return $order->fresh();
    }

    /** A complete, valid rating for $order. */
    private function payload(Order $order, array $overrides = []): array
    {
        $items = [];
        foreach ($order->orderItems as $line) {
            $items[$line->id] = ['rating' => 5, 'comment' => null];
        }

        return array_replace_recursive([
            'items' => $items,
            'rating_service' => 4,
            'rating_food' => 5,
            'rating' => 5,
            'comment' => 'Masarap!',
        ], $overrides);
    }

    // ---- who can rate ------------------------------------------------------------

    public function test_owner_can_rate_a_delivered_order_with_items_and_service(): void
    {
        $customer = $this->customer();
        $rider = $this->rider();
        $order = $this->menuOrder($customer, rider: $rider);
        [$first, $second] = $order->orderItems->all();

        $this->actingAs($customer)->get("/customer/orders/{$order->id}/feedback")
            ->assertOk()->assertSee('Rate all items the same')->assertSee('Delivery &amp; rider (Juan)', false);

        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order, [
            'items' => [$first->id => ['rating' => 4, 'comment' => '<b>Tender</b>   and  tasty'], $second->id => ['rating' => 2]],
            'comment' => '<script>alert(1)</script>Great   service',
        ]))->assertRedirect(route('customer.orders.show', $order->id))->assertSessionHas('success');

        $feedback = Feedback::sole();
        $this->assertSame([5, 4, 5], [$feedback->rating, $feedback->rating_service, $feedback->rating_food]);
        $this->assertSame($rider->id, $feedback->rider_id);
        $this->assertSame('alert(1)Great service', $feedback->comment); // tags stripped, spaces collapsed
        $this->assertSame(4, $feedback->items->firstWhere('order_item_id', $first->id)->rating);
        $this->assertSame('Tender and tasty', $feedback->items->firstWhere('order_item_id', $first->id)->comment);
        $this->assertSame($this->sinigang->id, $feedback->items->firstWhere('order_item_id', $second->id)->menu_item_id);
    }

    public function test_orders_not_yet_delivered_cannot_be_rated(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, 'out_for_delivery');

        $this->actingAs($customer)->get("/customer/orders/{$order->id}/feedback")
            ->assertRedirect(route('customer.orders.show', $order->id))
            ->assertSessionHas('error', 'You can rate this order once it has been delivered.');
        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order))->assertSessionHas('error');

        $this->assertSame(0, Feedback::count());
    }

    public function test_only_the_owner_can_rate(): void
    {
        $order = $this->menuOrder($this->customer());

        $this->actingAs($this->customer())->get("/customer/orders/{$order->id}/feedback")->assertNotFound();
        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order))->assertNotFound();
        $this->assertSame(0, Feedback::count());
    }

    public function test_unverified_customers_cannot_rate(): void
    {
        $customer = $this->customer(['email_verified_at' => null]);
        $order = $this->menuOrder($customer);

        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order))
            ->assertRedirect(route('verification.notice'));
        $this->assertSame(0, Feedback::count());
    }

    public function test_blocked_customers_cannot_submit_ratings(): void
    {
        $customer = $this->customer(['blocked_at' => now(), 'blocked_reason' => 'Fake bookings']);
        $order = $this->menuOrder($customer);

        $this->actingAs($customer)->get("/customer/orders/{$order->id}/feedback")->assertRedirect(route('customer.account-restricted'));
        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order))->assertRedirect(route('customer.account-restricted'));
        $this->assertSame(0, Feedback::count());
    }

    // ---- one per order, edits, windows -----------------------------------------------

    public function test_a_second_post_for_the_same_order_is_refused(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());

        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order))->assertSessionHas('success');
        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order, ['rating' => 1]))
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'already rated'));

        $this->assertSame(1, Feedback::count());
        $this->assertSame(5, Feedback::sole()->rating);
    }

    public function test_rating_can_be_edited_within_the_window_and_not_after(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());
        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order));

        Carbon::setTestNow(now()->addDays(6));
        $this->get("/customer/orders/{$order->id}/feedback")->assertOk()->assertSee('Edit your rating');
        $this->put("/customer/orders/{$order->id}/feedback", $this->payload($order, ['rating' => 3, 'comment' => 'Changed my mind']))
            ->assertSessionHas('success', 'Your rating was updated.');
        $this->assertSame(3, Feedback::sole()->rating);
        $this->assertNotNull(Feedback::sole()->edited_at);
        $this->assertSame(2, Feedback::sole()->items()->count()); // replaced, not duplicated

        Carbon::setTestNow(now()->addDays(2)); // day 8 > 7-day edit window
        $this->get("/customer/orders/{$order->id}/feedback")->assertSessionHas('error', fn ($m) => str_contains($m, 'time to edit it has passed'));
        $this->put("/customer/orders/{$order->id}/feedback", $this->payload($order, ['rating' => 1]))->assertSessionHas('error');
        $this->assertSame(3, Feedback::sole()->rating);
    }

    public function test_rating_closes_after_the_rating_window_and_follows_the_setting(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());
        $this->assertSame(1, Ratings::awaiting($customer)->count());

        Carbon::setTestNow(now()->addDays(31));
        $this->assertSame(Ratings::CLOSED, Ratings::status($order->fresh()));
        $this->assertSame(0, Ratings::awaiting($customer)->count());
        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Rating for this order closed'));
        $this->get("/customer/orders/{$order->id}")->assertOk()->assertDontSee('Rate your order');

        BookingSettings::save(['rating_window_days' => 60]);
        $this->assertSame(Ratings::OPEN, Ratings::status($order->fresh()));
        $this->get("/customer/orders/{$order->id}")->assertSee('Rate your order');
    }

    public function test_completed_at_follows_the_delivered_status(): void
    {
        $order = $this->menuOrder($this->customer(), 'out_for_delivery');
        $this->assertNull($order->completed_at);

        $order->update(['status' => 'delivered']);
        $this->assertEquals(now(), $order->fresh()->completed_at);

        $order->update(['status' => 'preparing']);
        $this->assertNull($order->fresh()->completed_at);
    }

    // ---- what must be rated -----------------------------------------------------------

    public function test_every_item_and_service_score_is_required(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());
        [$first, $second] = $order->orderItems->all();
        $payload = $this->payload($order);
        unset($payload['items'][$second->id], $payload['rating_service']);

        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $payload)
            ->assertSessionHasErrors(["items.{$second->id}.rating" => 'Please rate Sinigang.', 'rating_service']);
        $this->assertSame(0, Feedback::count());
    }

    public function test_menu_order_without_a_rider_skips_the_rider_score(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer);
        $payload = $this->payload($order);
        unset($payload['rating_service']);

        $this->actingAs($customer)->get("/customer/orders/{$order->id}/feedback")->assertDontSee('Delivery &amp; rider', false);
        $this->post("/customer/orders/{$order->id}/feedback", $payload)->assertSessionHasNoErrors();
        $this->assertNull(Feedback::sole()->rating_service);
        $this->assertNull(Feedback::sole()->rider_id);
    }

    public function test_completed_booking_rates_the_package_and_event_service(): void
    {
        $customer = $this->customer();
        $order = $this->bookingOrder($customer);

        $this->actingAs($customer)->get("/customer/orders/{$order->id}/feedback")
            ->assertOk()->assertSee('The package')->assertSee('Setup &amp; staff', false)->assertSee('Food quality')
            ->assertDontSee('Rate all items the same');

        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order))->assertSessionHasNoErrors();
        $this->assertSame($order->orderItems->first()->package_id, Feedback::sole()->items->sole()->package_id);
    }

    public function test_comment_length_and_invalid_stars_are_rejected(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());

        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order, [
            'comment' => str_repeat('a', 501), 'rating' => 6,
        ]))->assertSessionHasErrors(['comment', 'rating']);
        $this->assertSame(0, Feedback::count());
    }

    public function test_submissions_are_rate_limited(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, 'out_for_delivery'); // refused each time, still counted
        $this->actingAs($customer);

        for ($i = 0; $i < 5; $i++) {
            $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order));
        }
        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order))
            ->assertSessionHas('error', 'Too many rating submissions — please wait a minute and try again.');

        RateLimiter::clear('ratings:' . $customer->id);
    }

    // ---- photo --------------------------------------------------------------------------

    public function test_photo_is_stored_with_a_uuid_name_and_served_only_through_the_route(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());

        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order, [
            'photo' => UploadedFile::fake()->image('my food.jpg', 800, 600),
        ]))->assertSessionHasNoErrors();

        $feedback = Feedback::sole();
        $this->assertMatchesRegularExpression('#^ratings/[0-9a-f-]{36}\.(jpg|jpeg)$#', $feedback->photo_path);
        Storage::disk('public')->assertExists($feedback->photo_path);

        $this->get(route('ratings.photo', $feedback))->assertOk();
        $this->actingAs($this->customer())->get(route('ratings.photo', $feedback))->assertOk(); // visible review

        $feedback->update(['is_hidden' => true, 'hidden_reason' => 'Contains a phone number']);
        $this->actingAs($this->customer())->get(route('ratings.photo', $feedback))->assertNotFound();
        $this->actingAs($customer)->get(route('ratings.photo', $feedback))->assertOk(); // owner still sees it
        $this->actingAs($customer)->get("/customer/orders/{$order->id}")->assertSee('Hidden by Cojan')->assertDontSee('Contains a phone number'); // reason is admin-only
    }

    public function test_non_image_uploads_are_rejected(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());

        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order, [
            'photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('photo');
        $this->assertSame(0, Feedback::count());
    }

    public function test_replacing_the_photo_deletes_the_old_file(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());
        $this->actingAs($customer)->post("/customer/orders/{$order->id}/feedback", $this->payload($order, ['photo' => UploadedFile::fake()->image('a.jpg')]));
        $old = Feedback::sole()->photo_path;

        $this->put("/customer/orders/{$order->id}/feedback", $this->payload($order, ['photo' => UploadedFile::fake()->image('b.jpg')]));
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists(Feedback::sole()->photo_path);

        $this->put("/customer/orders/{$order->id}/feedback", $this->payload($order, ['remove_photo' => '1']));
        $this->assertNull(Feedback::sole()->photo_path);
    }

    // ---- ways in ------------------------------------------------------------------------

    public function test_rate_buttons_and_reminder_appear_for_unrated_delivered_orders(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer, rider: $this->rider());
        $this->actingAs($customer);

        $this->get('/customer/orders')->assertOk()
            ->assertSee("How was your order #{$order->order_number}?")
            ->assertSee(route('customer.feedback.create', $order->id));
        $this->get('/customer/dashboard')->assertOk()->assertSee('We\'d love your rating', false);
        $this->get("/customer/orders/{$order->id}")->assertSee('Rate your order');

        $this->post("/customer/orders/{$order->id}/feedback", $this->payload($order));
        $this->get('/customer/orders')->assertDontSee('We\'d love your rating', false);
        $this->get("/customer/orders/{$order->id}")->assertSee('Your rating')->assertSee('Edit rating');
    }

    public function test_delivered_email_links_to_the_rating_page(): void
    {
        $order = $this->menuOrder($this->customer());

        (new OrderStatusMail($order))->assertSeeInHtml(route('customer.feedback.create', $order->id))
            ->assertSeeInHtml('Rate your order');
        (new OrderStatusMail($this->menuOrder($this->customer(), 'out_for_delivery')))
            ->assertDontSeeInHtml('Rate your order');
    }

    public function test_older_feedback_without_item_ratings_still_shows(): void
    {
        $customer = $this->customer();
        $order = $this->menuOrder($customer);
        Feedback::create(['user_id' => $customer->id, 'order_id' => $order->id, 'rating' => 4, 'comment' => 'Old style']);

        $this->actingAs($customer)->get("/customer/orders/{$order->id}")->assertOk()
            ->assertSee('Your rating')->assertSee('Old style');
    }
}
