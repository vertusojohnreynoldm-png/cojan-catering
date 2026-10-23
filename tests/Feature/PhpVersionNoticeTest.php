<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The temporary PHP-version line (Oct 2026 PHP security release) must only
// ever be visible to admins.
class PhpVersionNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_the_php_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertSee('Server PHP ' . PHP_VERSION);
    }

    public function test_php_version_is_never_shown_to_customers_riders_or_guests(): void
    {
        foreach (['customer', 'delivery'] as $role) {
            $user = User::factory()->create(['role' => $role, 'email_verified_at' => now()]);
            $this->actingAs($user)->get('/admin/dashboard')->assertRedirect();
            $this->actingAs($user)->followingRedirects()->get('/admin/dashboard')->assertDontSee('Server PHP');
        }

        auth()->logout();
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
        $this->get('/')->assertDontSee('Server PHP');
    }
}
