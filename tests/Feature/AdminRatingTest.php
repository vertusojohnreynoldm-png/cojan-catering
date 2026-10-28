<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\RatingStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

// Ratings, Stage 3: the admin Ratings page (filters, averages, reply,
// hide/unhide with a reason — all logged) and the dashboard low-ratings widget.
class AdminRatingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rider;
    private MenuItem $adobo;
    private MenuItem $flan;
    private CateringPackage $fiesta;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));

        $this->admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now(), 'name' => 'Admin Ana']);
        $this->rider = User::factory()->create(['role' => 'delivery', 'email_verified_at' => now(), 'name' => 'Rider Rico']);
        $category = Category::create(['name' => 'Mains', 'is_active' => true]);
        $this->adobo = MenuItem::create(['category_id' => $category->id, 'name' => 'Adobo', 'description' => 'x', 'price' => 150, 'is_available' => true]);
        $this->flan = MenuItem::create(['category_id' => $category->id, 'name' => 'Leche Flan', 'description' => 'x', 'price' => 80, 'is_available' => true]);
        $this->fiesta = CateringPackage::create(['name' => 'Fiesta', 'pax' => 50, 'price' => 15000, 'is_available' => true]);
    }

    private function customer(string $name = 'Carla Cruz'): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now(), 'name' => $name]);
    }

    /** A rating: overall/service/food scores, one item line (menu item or package). */
    private function rating(array $scores = [], ?MenuItem $item = null, bool $package = false, array $extra = [], ?User $customer = null): Feedback
    {
        $customer ??= $this->customer();
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'delivered', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'paid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567',
            'event_date' => $package ? now()->subDay() : null,
        ]);
        $feedback = Feedback::create(array_merge([
            'user_id' => $customer->id, 'order_id' => $order->id,
            'rating' => $scores['overall'] ?? 5, 'rating_food' => $scores['food'] ?? 5,
            'rating_service' => $scores['service'] ?? 5, 'rider_id' => $package ? null : $this->rider->id,
        ], $extra));
        $feedback->items()->create([
            'menu_item_id' => $package ? null : ($item ?? $this->adobo)->id,
            'package_id' => $package ? $this->fiesta->id : null,
            'rating' => $scores['item'] ?? 5,
        ]);

        return $feedback;
    }

    // ---- access and URLs ---------------------------------------------------------------

    public function test_old_feedback_url_redirects_and_nav_says_ratings(): void
    {
        $this->actingAs($this->admin)->get('/admin/feedback?stars=low')
            ->assertStatus(301)->assertRedirect(route('admin.ratings', ['stars' => 'low']));

        $this->get(route('admin.ratings'))->assertOk()
            ->assertSee('<i class="bi bi-star"></i> Ratings', false)
            ->assertDontSee('<i class="bi bi-chat-square-text"></i> Feedback', false);
    }

    public function test_only_admins_can_use_the_ratings_page_and_actions(): void
    {
        $rating = $this->rating();
        $customer = $this->customer('Other Person');

        $this->actingAs($customer)->get(route('admin.ratings'))->assertRedirect(route('customer.dashboard'));
        $this->post(route('admin.ratings.hide', $rating), ['reason' => 'x'])->assertRedirect(route('customer.dashboard'));
        $this->post(route('admin.ratings.reply', $rating), ['reply' => 'x'])->assertRedirect(route('customer.dashboard'));
        $this->assertFalse($rating->fresh()->is_hidden);
        $this->assertNull($rating->fresh()->admin_reply);
    }

    // ---- averages and filters ----------------------------------------------------------

    public function test_averages_per_item_package_and_rider_count_visible_ratings_only(): void
    {
        $this->rating(['item' => 5, 'service' => 4]);
        $this->rating(['item' => 3, 'service' => 2]);
        $this->rating(['item' => 1, 'service' => 1], extra: ['is_hidden' => true, 'hidden_reason' => 'Spam']);
        $this->rating(['item' => 4], package: true);

        $response = $this->actingAs($this->admin)->get(route('admin.ratings'))->assertOk();
        $items = $response->viewData('itemAverages')->keyBy('id');
        $this->assertSame(['avg' => 4.0, 'count' => 2], array_intersect_key($items[$this->adobo->id], ['avg' => 1, 'count' => 1]));
        $this->assertSame(4.0, $response->viewData('packageAverages')->firstWhere('id', $this->fiesta->id)['avg']);
        $rider = $response->viewData('riderAverages')->firstWhere('id', $this->rider->id);
        $this->assertSame([3.0, 2, 'Rider Rico'], [$rider['avg'], $rider['count'], $rider['name']]);
        $response->assertSee('Rider Rico')->assertSee('Fiesta');
    }

    public function test_filters_by_item_package_rider_stars_visibility_and_dates(): void
    {
        $adobo5 = $this->rating(['overall' => 5], $this->adobo);
        $flan2 = $this->rating(['overall' => 2], $this->flan);
        $pkg = $this->rating(['overall' => 4], package: true);
        $lowItemOnly = $this->rating(['overall' => 5, 'item' => 1], $this->flan);
        $hidden = $this->rating(['overall' => 3], $this->adobo, extra: ['is_hidden' => true, 'hidden_reason' => 'x']);
        Carbon::setTestNow(now()->subDays(40));
        $old = $this->rating(['overall' => 5], $this->adobo);
        Carbon::setTestNow(now()->addDays(40));

        $ids = fn (array $query) => $this->actingAs($this->admin)->get(route('admin.ratings', $query))
            ->assertOk()->viewData('ratings')->pluck('id')->sort()->values()->all();
        $sorted = fn (...$ratings) => collect($ratings)->pluck('id')->sort()->values()->all();

        $this->assertSame($sorted($adobo5, $hidden, $old), $ids(['item' => $this->adobo->id]));
        $this->assertSame($sorted($pkg), $ids(['package' => $this->fiesta->id]));
        $this->assertSame($sorted($adobo5, $flan2, $lowItemOnly, $hidden, $old), $ids(['rider' => $this->rider->id]));
        $this->assertSame($sorted($flan2), $ids(['stars' => 2]));
        $this->assertSame($sorted($flan2, $lowItemOnly), $ids(['stars' => 'low']));
        $this->assertSame($sorted($hidden), $ids(['visibility' => 'hidden']));
        $this->assertSame($sorted($old), $ids(['to' => now()->subDays(30)->toDateString()]));
        $this->assertNotContains($old->id, $ids(['from' => now()->subDays(7)->toDateString()]));

        $this->get(route('admin.ratings', ['from' => '2026-11-04', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
    }

    // ---- reply ---------------------------------------------------------------------------

    public function test_admin_reply_is_saved_logged_and_shown_to_the_customer(): void
    {
        $customer = $this->customer();
        $rating = $this->rating(['item' => 4], customer: $customer);

        $this->actingAs($this->admin)->post(route('admin.ratings.reply', $rating), [
            'reply' => '<b>Salamat</b>   po!', 'return' => route('admin.ratings', ['stars' => 4]),
        ])->assertRedirect(route('admin.ratings', ['stars' => 4]) . '#rating-' . $rating->id);

        $rating->refresh();
        $this->assertSame('Salamat po!', $rating->admin_reply);
        $this->assertSame($this->admin->id, $rating->replied_by);
        $log = ActivityLog::where('action', 'rating.replied')->sole();
        $this->assertSame('admin', $log->category);
        $this->assertStringContainsString('Admin Ana replied to Carla Cruz\'s rating on Order #' . $rating->order->order_number, $log->description);

        $this->actingAs($customer)->get(route('customer.orders.show', $rating->order_id))->assertSee('Reply from Cojan')->assertSee('Salamat po!');
        $this->get(route('customer.menu.show', $this->adobo->id))->assertSee('Salamat po!');

        $this->actingAs($this->admin)->post(route('admin.ratings.reply', $rating), ['reply' => 'Updated reply']);
        $this->assertSame('rating.reply_updated', ActivityLog::latest('id')->first()->action);

        $this->post(route('admin.ratings.reply', $rating), ['reply' => '']);
        $this->assertNull($rating->fresh()->admin_reply);
        $this->assertSame('rating.reply_removed', ActivityLog::latest('id')->first()->action);

        $this->post(route('admin.ratings.reply', $rating), ['reply' => str_repeat('a', 1001)])->assertSessionHasErrors('reply');
    }

    public function test_an_outside_return_url_is_ignored(): void
    {
        $rating = $this->rating();

        $this->actingAs($this->admin)->post(route('admin.ratings.reply', $rating), ['reply' => 'Thanks', 'return' => 'https://evil.example/phish'])
            ->assertRedirect(route('admin.ratings') . '#rating-' . $rating->id);
    }

    // ---- hide / unhide ---------------------------------------------------------------------

    public function test_hiding_needs_a_reason_and_removes_it_from_customers_and_averages(): void
    {
        $customer = $this->customer();
        $rating = $this->rating(['item' => 1], customer: $customer, extra: ['comment' => 'Call me at 09171234567']);
        $this->rating(['item' => 5]);
        $this->assertSame(['avg' => 3.0, 'count' => 2], RatingStats::forMenuItem($this->adobo->id));

        $this->actingAs($this->admin)->post(route('admin.ratings.hide', $rating), ['reason' => '  '])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($rating->fresh()->is_hidden);

        $this->post(route('admin.ratings.hide', $rating), ['reason' => 'Contains a phone number'])->assertSessionHas('success');
        $rating->refresh();
        $this->assertTrue($rating->is_hidden);
        $this->assertSame('Contains a phone number', $rating->hidden_reason);
        $this->assertSame($this->admin->id, $rating->hidden_by);
        $this->assertSame(['avg' => 5.0, 'count' => 1], RatingStats::forMenuItem($this->adobo->id));

        $log = ActivityLog::where('action', 'rating.hidden')->sole();
        $this->assertStringContainsString('Contains a phone number', $log->description);
        $this->assertSame(['old' => null, 'new' => 'Contains a phone number'], $log->changes['reason']);

        $this->get(route('admin.ratings'))->assertSee('Hidden by Admin Ana')->assertSee('Contains a phone number');
        $this->actingAs($customer)->get(route('customer.orders.show', $rating->order_id))
            ->assertSee('Hidden by Cojan')->assertDontSee('Contains a phone number');
        $this->get(route('customer.menu.show', $this->adobo->id))->assertDontSee('Call me at');
    }

    public function test_unhiding_needs_a_reason_and_is_logged(): void
    {
        $rating = $this->rating(extra: ['is_hidden' => true, 'hidden_reason' => 'Looked like spam', 'hidden_at' => now(), 'hidden_by' => $this->admin->id]);

        $this->actingAs($this->admin)->post(route('admin.ratings.unhide', $rating), [])->assertSessionHasErrors('reason');
        $this->assertTrue($rating->fresh()->is_hidden);

        $this->post(route('admin.ratings.unhide', $rating), ['reason' => 'Checked — genuine review'])->assertSessionHas('success');
        $rating->refresh();
        $this->assertFalse($rating->is_hidden);
        $this->assertNull($rating->hidden_reason);
        $log = ActivityLog::where('action', 'rating.unhidden')->sole();
        $this->assertSame(['old' => 'Looked like spam', 'new' => 'Checked — genuine review'], $log->changes['reason']);
    }

    public function test_admin_can_see_a_hidden_ratings_photo(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('p.jpg')->store('ratings', 'public');
        $rating = $this->rating(extra: ['photo_path' => $path, 'is_hidden' => true, 'hidden_reason' => 'x']);

        $this->actingAs($this->admin)->get(route('admin.ratings'))->assertSee(route('ratings.photo', $rating));
        $this->get(route('ratings.photo', $rating))->assertOk();
        $this->actingAs($this->customer('Someone Else'))->get(route('ratings.photo', $rating))->assertNotFound();
    }

    // ---- dashboard -------------------------------------------------------------------------

    public function test_dashboard_highlights_recent_low_ratings(): void
    {
        $low = $this->rating(['service' => 2], extra: ['comment' => 'Rider was very late']);
        $this->rating(['overall' => 5]);
        Carbon::setTestNow(now()->subDays(45));
        $this->rating(['overall' => 1], extra: ['comment' => 'Old complaint']);
        Carbon::setTestNow(now()->addDays(45));

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Low ratings · last 30 days')
            ->assertSee('Rider was very late')->assertDontSee('Old complaint')
            ->assertSee('#rating-' . $low->id, false);
        $this->assertSame([$low->id], $response->viewData('lowRatings')->pluck('id')->all());
    }

    public function test_dashboard_says_so_when_there_are_no_low_ratings(): void
    {
        $this->rating(['overall' => 5]);

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('No 1–2 star ratings in the last 30 days.');
    }
}
