<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnpaidBookingExpirer;
use App\Models\ActivityLog;
use App\Models\Feedback;
use App\Models\Order;
use App\Models\User;
use App\Models\MenuItem;

class DashboardController extends Controller
{
    public function index()
    {
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

        $totalOrders    = Order::count();
        $pendingOrders  = Order::where('status', 'pending')->count();
        $totalCustomers = User::where('role', 'customer')->count();
        $totalRevenue   = Order::where('status', 'delivered')->sum('total_amount');
        $recentOrders   = Order::with('user')->orderBy('created_at', 'desc')->take(5)->get();
        $recentActivity = ActivityLog::orderBy('created_at', 'desc')->take(5)->get();
        // 1–2 stars anywhere in a rating, last 30 days — highlighted for follow-up.
        $lowRatings = Feedback::low()->with('user', 'order', 'items')
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()->take(5)->get();

        return view('admin.dashboard', compact(
            'totalOrders',
            'pendingOrders',
            'totalCustomers',
            'totalRevenue',
            'recentOrders',
            'recentActivity',
            'lowRatings'
        ));
    }
}
