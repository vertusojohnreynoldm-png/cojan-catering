<?php

namespace Tests\Feature;

use App\Models\CateringPackage;
use App\Models\Category;
use App\Models\Feedback;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Services\RatingStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

// Ratings, Stage 2: averages on menu/package cards (visible reviews only,
// cached), the menu item page and the package page with recent reviews.
class RatingDisplayTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $adobo;
    private CateringPackage $fiesta;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-04 10:00:00', 'Asia/Manila'));

        $this->adobo = MenuItem::create([
            'category_id' => Category::create(['name' => 'Mains', 'is_active' => true])->id,
            'name' => 'Adobo', 'description' => 'Braised pork', 'price' => 150, 'is_available' => true,
        ]);
        $this->fiesta = CateringPackage::create(['name' => 'Fiesta', 'pax' => 50, 'price' => 15000, 'is_available' => true]);
    }

    private function customer(string $name = 'Juan Santos'): User
    {
        return User::factory()->create(['role' => 'customer', 'email_verified_at' => now(), 'name' => $name]);
    }

    /** A rating of the Adobo (or the Fiesta package) with the given stars. */
    private function rate(int $stars, array $feedback = [], array $item = [], bool $package = false): Feedback
    {
        $customer = $this->customer($feedback['name'] ?? 'Juan Santos');
        unset($feedback['name']);
        $order = Order::create([
            'user_id' => $customer->id, 'order_number' => 'ORD-' . Str::upper(Str::random(8)),
            'status' => 'delivered', 'payment_method' => 'cash_on_delivery', 'payment_status' => 'paid',
            'subtotal' => 150, 'delivery_fee' => 50, 'total_amount' => 200,
            'delivery_address' => 'x', 'delivery_phone' => '09171234567',
        ]);
        $rating = Feedback::create(array_merge([
            'user_id' => $customer->id, 'order_id' => $order->id, 'rating' => 5, 'rating_food' => 5,
        ], $feedback));
        $rating->items()->create(array_merge([
            'menu_item_id' => $package ? null : $this->adobo->id,
            'package_id' => $package ? $this->fiesta->id : null,
            'rating' => $stars,
        ], $item));

        return $rating;
    }

    // ---- averages ---------------------------------------------------------------------

    public function test_averages_count_visible_reviews_only(): void
    {
        $this->rate(5);
        $this->rate(4);
        $this->rate(1, ['is_hidden' => true, 'hidden_reason' => 'Spam']);

        $this->assertSame(['avg' => 4.5, 'count' => 2], RatingStats::forMenuItem($this->adobo->id));
        $this->assertSame([5 => 1, 4 => 1, 3 => 0, 2 => 0, 1 => 0], RatingStats::breakdown('menu_item_id', $this->adobo->id));
        $this->assertNull(RatingStats::forPackage($this->fiesta->id));
    }

    public function test_menu_cards_show_the_average_or_no_ratings_yet(): void
    {
        $other = MenuItem::create(['category_id' => $this->adobo->category_id, 'name' => 'Leche Flan', 'description' => 'x', 'price' => 80, 'is_available' => true]);
        $this->rate(5);
        $this->rate(4);

        $this->actingAs($this->customer())->get('/customer/menu')->assertOk()
            ->assertSee('Rated 4.5 out of 5 from 2 ratings')
            ->assertSee('<strong>4.5</strong> (2)', false)
            ->assertSee('No ratings yet')
            ->assertSee(route('customer.menu.show', $other->id));
    }

    public function test_averages_are_cached_and_refreshed_when_a_rating_changes(): void
    {
        $first = $this->rate(5);
        RatingStats::all(); // warm the cache

        DB::enableQueryLog();
        RatingStats::all();
        RatingStats::forMenuItem($this->adobo->id);
        $this->assertCount(0, array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'feedback_items')),
            'cached averages should not query the ratings tables');
        DB::disableQueryLog();

        $this->rate(3);
        $this->assertSame(['avg' => 4.0, 'count' => 2], RatingStats::forMenuItem($this->adobo->id));

        $first->update(['is_hidden' => true]);
        $this->assertSame(['avg' => 3.0, 'count' => 1], RatingStats::forMenuItem($this->adobo->id));

        $first->update(['is_hidden' => false]);
        $first->delete();
        $this->assertSame(['avg' => 3.0, 'count' => 1], RatingStats::forMenuItem($this->adobo->id));
    }

    // ---- menu item page -----------------------------------------------------------------

    public function test_menu_item_page_shows_recent_visible_reviews_and_replies(): void
    {
        $this->rate(5, ['comment' => 'Whole order was great', 'admin_reply' => 'Salamat po!', 'replied_at' => now()], ['comment' => 'Super tender']);
        $this->rate(2, ['name' => 'Maria Santos', 'is_hidden' => true, 'hidden_reason' => 'Rude'], ['comment' => 'Hidden words']);

        $this->actingAs($this->customer('Viewer Person'))->get(route('customer.menu.show', $this->adobo->id))->assertOk()
            ->assertSee('Adobo')->assertSee('Braised pork')
            ->assertSee('Juan S.')->assertDontSee('Juan Santos')
            ->assertSee('Super tender')->assertSee('Whole order was great')
            ->assertSee('Reply from Cojan')->assertSee('Salamat po!')
            ->assertDontSee('Hidden words')->assertDontSee('Maria S.')
            ->assertSee('Rated 5.0 out of 5 from 1 rating')
            ->assertSee('name="quantity"', false); // add to cart from the item page
    }

    public function test_menu_item_page_without_ratings_says_no_ratings_yet(): void
    {
        $this->actingAs($this->customer())->get(route('customer.menu.show', $this->adobo->id))->assertOk()
            ->assertSee('No ratings yet.')->assertSee('Be the first to rate this dish');
    }

    public function test_menu_item_reviews_are_paginated(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->rate(4, [], ['comment' => "Review number {$i}"]);
            Carbon::setTestNow(now()->addMinute());
        }

        $this->actingAs($this->customer())->get(route('customer.menu.show', $this->adobo->id))
            ->assertSee('Review number 8')->assertDontSee('Review number 2')->assertSee('Older →');
        $this->get(route('customer.menu.show', $this->adobo->id) . '?page=2')
            ->assertSee('Review number 2')->assertSee('← Newer');
    }

    public function test_unavailable_item_page_shows_a_notice_instead_of_add_to_cart(): void
    {
        $this->adobo->update(['is_available' => false]);

        $this->actingAs($this->customer())->get(route('customer.menu.show', $this->adobo->id))->assertOk()
            ->assertSee("isn't available right now", false)->assertDontSee('name="quantity"', false);
    }

    // ---- packages -----------------------------------------------------------------------

    public function test_package_cards_and_page_show_ratings_and_reviews(): void
    {
        $this->rate(4, ['comment' => 'Setup was fast'], ['comment' => 'Generous servings'], package: true);

        $this->actingAs($this->customer())->get('/customer/packages')->assertOk()
            ->assertSee('Rated 4.0 out of 5 from 1 rating')
            ->assertSee(route('customer.packages.show', $this->fiesta->id) . '#reviews-heading');

        $this->get(route('customer.packages.show', $this->fiesta->id))->assertOk()
            ->assertSee('Fiesta')->assertSee('Generous servings')->assertSee('Setup was fast')
            ->assertSee(route('customer.packages.index', ['avail' => $this->fiesta->id]));
    }

    public function test_package_page_without_ratings_and_unavailable_packages(): void
    {
        $this->actingAs($this->customer())->get(route('customer.packages.show', $this->fiesta->id))
            ->assertOk()->assertSee('Be the first to rate this package');

        $this->fiesta->update(['is_available' => false]);
        $this->get(route('customer.packages.show', $this->fiesta->id))->assertNotFound();
        $this->get('/customer/packages/not-a-number')->assertNotFound();
    }

    public function test_avail_link_reopens_the_booking_modal_on_the_packages_page(): void
    {
        $this->actingAs($this->customer())->get('/customer/packages?avail=' . $this->fiesta->id)->assertOk()
            ->assertSee('availButton.click()', false);
    }
}
