<?php

use App\Http\Controllers\Customer\FeedbackController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Delivery\OrderController as DeliveryOrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\MenuController as AdminMenuController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Customer\MenuController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Delivery\DashboardController as DeliveryDashboard;
use App\Http\Controllers\ChatController;

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
});

// Admin routes
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Orders
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{id}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/orders/{id}/assign', [AdminOrderController::class, 'assignDelivery'])->name('orders.assign');

    // Menu
    Route::get('/menu', [AdminMenuController::class, 'index'])->name('menu');
    Route::post('/menu', [AdminMenuController::class, 'store'])->name('menu.store');
    Route::get('/menu/{id}/edit', [AdminMenuController::class, 'edit'])->name('menu.edit');
    Route::post('/menu/{id}', [AdminMenuController::class, 'update'])->name('menu.update');
    Route::get('/menu/{id}/delete', [AdminMenuController::class, 'destroy'])->name('menu.destroy');

    // Users
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}/delete', [AdminUserController::class, 'destroy'])->name('users.destroy');

    // Inventory
    Route::get('/inventory', [AdminInventoryController::class, 'index'])->name('inventory');
    Route::post('/inventory', [AdminInventoryController::class, 'store'])->name('inventory.store');
    Route::post('/inventory/{id}', [AdminInventoryController::class, 'update'])->name('inventory.update');
    Route::post('/inventory/{id}/add', [AdminInventoryController::class, 'addStock'])->name('inventory.add');

    // Analytics
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics');

    // Feedback
    Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback');

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

    // Cart
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/update/{id}', [CartController::class, 'update'])->name('cart.update');
    Route::get('/cart/remove/{id}', [CartController::class, 'remove'])->name('cart.remove');
    Route::get('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
    Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show');

    // Feedback
    Route::get('/orders/{id}/feedback', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('/orders/{id}/feedback', [FeedbackController::class, 'store'])->name('feedback.store');

    // Chat
    Route::get('/chat/messages', [ChatController::class, 'customerMessages'])->name('chat.messages');
    Route::get('/chat/unread', [ChatController::class, 'unreadCount'])->name('chat.unread');
    Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');
});

// Order tracking (public - accessible via QR code)
Route::get('/track/{orderNumber}', [OrderController::class, 'track'])->name('customer.orders.track');

// Delivery routes
Route::middleware(['auth', 'role:delivery'])->prefix('delivery')->name('delivery.')->group(function () {
    Route::get('/dashboard', [DeliveryDashboard::class, 'index'])->name('dashboard');
    Route::get('/orders/{id}', [DeliveryOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{id}/status', [DeliveryOrderController::class, 'updateStatus'])->name('orders.status');
    Route::post('/scan', [DeliveryOrderController::class, 'scan'])->name('scan');
});

require __DIR__.'/auth.php';