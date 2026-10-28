<?php

use App\Http\Controllers\Customer\FeedbackController;
use App\Http\Controllers\RatingPhotoController;
use App\Http\Controllers\TrackLinkController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\RatingController as AdminRatingController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Delivery\OrderController as DeliveryOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\BookingRulesController as AdminBookingRulesController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Admin\UtensilController as AdminUtensilController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\PackageController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Delivery\DashboardController as DeliveryDashboard;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ProofPhotoController;
use App\Http\Controllers\ReceiptController;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($user->role === 'delivery') {
            return redirect()->route('delivery.dashboard');
        } else {
            return redirect()->route('customer.dashboard');
        }
    }
    return view('welcome');
})->name('welcome');

// Shared across customer/admin/delivery — authorization is checked inside
// the controller itself (owner, assigned rider, or admin), since no single
// role-prefixed group fits a route all three need.
Route::get('/orders/{order}/proof-photo', [ProofPhotoController::class, 'show'])
    ->middleware('auth')
    ->name('orders.proof-photo');

// A rating's photo — hidden reviews' photos only for their owner and admins.
Route::get('/ratings/{feedback}/photo', [RatingPhotoController::class, 'show'])
    ->middleware('auth')
    ->name('ratings.photo');

// GCash downpayment receipt — owner or admin only (checked in the controller).
Route::get('/orders/{order}/receipt', [ReceiptController::class, 'show'])
    ->middleware('auth')
    ->name('orders.receipt');

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{id}/assign', [AdminOrderController::class, 'assignDelivery'])->name('orders.assign');
    Route::post('/orders/{id}/mark-paid', [AdminOrderController::class, 'markPaymentPaid'])->name('orders.mark-paid');
    Route::post('/orders/{id}/flag', [AdminOrderController::class, 'flag'])->name('orders.flag');
    Route::post('/orders/{id}/reject-receipt', [AdminOrderController::class, 'rejectReceipt'])->name('orders.reject-receipt');

    // Catering Bookings (the Avail flow) — separate list, but reuses the
    // Orders detail page/status/mark-paid routes above since those already
    // branch on Order::isBooking() and need no booking-specific variants.
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings');

    // Booking Rules — capacity/lead-time settings and blocked dates, two
    // tabs of one page.
    Route::get('/booking-rules', [AdminBookingRulesController::class, 'index'])->name('booking-rules');
    Route::post('/booking-rules', [AdminBookingRulesController::class, 'updateRules'])->name('booking-rules.update');
    Route::get('/blocked-dates', [AdminBookingRulesController::class, 'blockedDates'])->name('blocked-dates');
    Route::post('/blocked-dates', [AdminBookingRulesController::class, 'storeBlockedDate'])->name('blocked-dates.store');
    Route::delete('/blocked-dates/{id}', [AdminBookingRulesController::class, 'destroyBlockedDate'])->name('blocked-dates.destroy');

    // Menu
    Route::get('/menu', [AdminMenuController::class, 'index'])->name('menu');
    Route::post('/menu', [AdminMenuController::class, 'store'])->name('menu.store');
    Route::get('/menu/{id}/edit', [AdminMenuController::class, 'edit'])->name('menu.edit');
    Route::post('/menu/{id}', [AdminMenuController::class, 'update'])->name('menu.update');
    Route::get('/menu/{id}/delete', [AdminMenuController::class, 'destroy'])->name('menu.destroy');

    // Packages
    Route::get('/packages', [AdminPackageController::class, 'index'])->name('packages');
    Route::post('/packages', [AdminPackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{id}/edit', [AdminPackageController::class, 'edit'])->name('packages.edit');
    Route::post('/packages/{id}', [AdminPackageController::class, 'update'])->name('packages.update');
    Route::get('/packages/{id}/delete', [AdminPackageController::class, 'destroy'])->name('packages.destroy');

    // Utensils
    Route::get('/utensils', [AdminUtensilController::class, 'index'])->name('utensils');
    Route::post('/utensils', [AdminUtensilController::class, 'store'])->name('utensils.store');
    Route::post('/utensils/{id}', [AdminUtensilController::class, 'update'])->name('utensils.update');
    Route::post('/utensils/{id}/add', [AdminUtensilController::class, 'addStock'])->name('utensils.add');

    // Users
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/delete', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{id}/block', [AdminUserController::class, 'block'])->name('users.block');
    Route::post('/users/{id}/unblock', [AdminUserController::class, 'unblock'])->name('users.unblock');

    // Inventory
    Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory');
    Route::post('/inventory', [AdminInventoryController::class, 'store'])->name('inventory.store');
    Route::post('/inventory/{id}', [AdminInventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{id}/add', [AdminInventoryController::class, 'addStock'])->name('inventory.add');

    // Analytics
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics');

    // Ratings (was "Feedback" — the old URL still works and redirects here)
    Route::get('/ratings', [AdminRatingController::class, 'index'])->name('ratings');
    Route::post('/ratings/{feedback}/reply', [AdminRatingController::class, 'reply'])->name('ratings.reply');
    Route::post('/ratings/{feedback}/hide', [AdminRatingController::class, 'hide'])->name('ratings.hide');
    Route::post('/ratings/{feedback}/unhide', [AdminRatingController::class, 'unhide'])->name('ratings.unhide');
    Route::get('/feedback', fn () => redirect()->route('admin.ratings', request()->query(), 301))->name('feedback');

    // Activity Log (audit trail) — read-only, admin-only (already scoped by
    // this group's auth+role:admin middleware).
    Route::get('/activity', [AdminActivityLogController::class, 'index'])->name('activity');
    Route::get('/activity/export', [AdminActivityLogController::class, 'export'])->name('activity.export');

    // Chat
    Route::get('/chat/inbox', [ChatController::class, 'adminInbox'])->name('chat.inbox');
    Route::get('/chat/messages/{customerId}', [ChatController::class, 'adminMessages'])->name('chat.messages');
    Route::get('/chat/unread', [ChatController::class, 'unreadCount'])->name('chat.unread');
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
});

// Customer routes
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [CustomerDashboard::class, 'index'])->name('dashboard');

    // Menu
    Route::get('/menu', [MenuController::class, 'index'])->name('menu');
    Route::get('/menu/{id}', [MenuController::class, 'show'])->name('menu.show');
    Route::get('/menu/category/{categoryId}', [MenuController::class, 'byCategory'])->name('menu.category');

    // Packages
    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    // One package: details + ratings & reviews. Numeric only, so it never
    // shadows /packages/checkout.
    Route::get('/packages/{id}', [PackageController::class, 'show'])->whereNumber('id')->name('packages.show');

    // Package booking (Avail flow) — separate from the regular cart checkout.
    // 'verified' gates the actual booking actions, not browsing the package
    // list above.
    Route::post('/packages/avail', [PackageController::class, 'storeBooking'])->middleware(['verified', 'not-blocked'])->name('packages.avail');
    Route::get('/packages/checkout', [PackageController::class, 'checkout'])->middleware(['verified', 'not-blocked'])->name('packages.checkout');
    Route::post('/packages/checkout', [PackageController::class, 'store'])->middleware(['verified', 'not-blocked'])->name('packages.checkout.store');

    // Cart
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/add-package', [CartController::class, 'addPackage'])->name('cart.add-package');
    Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
    Route::get('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
    Route::get('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

    // Where 'not-blocked' sends a blocked customer — a polite explanation.
    Route::view('/account-restricted', 'customer.account-restricted')->name('account-restricted');

    // Orders — 'verified' gates placing an order, not viewing past ones
    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/checkout', [OrderController::class, 'checkout'])->middleware(['verified', 'not-blocked'])->name('checkout');
    Route::get('/checkout/delivery-slots', [OrderController::class, 'deliverySlots'])->middleware(['verified', 'not-blocked'])->name('checkout.delivery-slots');
    Route::post('/orders', [OrderController::class, 'store'])->middleware(['verified', 'not-blocked'])->name('orders.store');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');
    // Paying for an existing booking is allowed even if the customer was
    // blocked since — it isn't placing a new order.
    Route::post('/orders/{id}/receipt', [ReceiptController::class, 'store'])->name('orders.receipt.store');
    Route::post('/orders/{id}/confirm-received', [OrderController::class, 'confirmReceived'])->name('orders.confirm-received');

    // Ratings — verified, not blocked, and rate-limited (AppServiceProvider).
    // Who may rate which order, and until when: App\Services\Ratings.
    Route::middleware(['verified', 'not-blocked'])->group(function () {
        Route::get('/orders/{id}/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
        Route::post('/orders/{id}/feedback', [FeedbackController::class, 'store'])->middleware('throttle:ratings')->name('feedback.store');
        Route::put('/orders/{id}/feedback', [FeedbackController::class, 'update'])->middleware('throttle:ratings')->name('feedback.update');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Chat
    Route::get('/chat/messages', [ChatController::class, 'customerMessages'])->name('chat.messages');
    Route::get('/chat/unread', [ChatController::class, 'unreadCount'])->name('chat.unread');
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
});

// Order tracking = the link inside every order QR (requires login — scanning
// the QR while logged out redirects through login and lands back here via
// redirect()->intended()). Customers get their tracking page; the ASSIGNED
// rider records "QR verified at hand-off" (see TrackLinkController).
Route::middleware(['auth'])
    ->get('/track/{orderNumber}', TrackLinkController::class)
    ->name('customer.orders.track');

// Live tracking data for the tracking page (menu orders). Accepts no
// coordinates — the server uses the rider's stored location. Same per-user
// limiter as the rider's route endpoint.
Route::middleware(['auth', 'role:customer', 'throttle:routing'])
    ->get('/track/{orderNumber}/live', [OrderController::class, 'trackRoute'])
    ->name('customer.orders.track.live');

// Delivery routes
Route::middleware(['auth', 'role:delivery'])->prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/dashboard', [DeliveryDashboard::class, 'index'])->name('dashboard');
    Route::get('/orders/{id}', [DeliveryOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/status', [DeliveryOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{id}/location', [DeliveryOrderController::class, 'updateLocation'])->name('orders.location');
    // Road route to the customer's pin — rate-limited per rider (AppServiceProvider).
    Route::get('/orders/{id}/route', [DeliveryOrderController::class, 'route'])->middleware('throttle:routing')->name('orders.route');
    Route::post('/scan', [DeliveryOrderController::class, 'scan'])->name('scan');
    // In-app "Scan customer QR" + the GPS fix sent after a phone-camera scan.
    Route::post('/orders/{id}/verify-qr', [DeliveryOrderController::class, 'verifyQr'])->middleware('throttle:20,1')->name('orders.verify-qr');
    Route::post('/orders/{id}/qr-location', [DeliveryOrderController::class, 'qrLocation'])->middleware('throttle:20,1')->name('orders.qr-location');
});

require __DIR__.'/auth.php';