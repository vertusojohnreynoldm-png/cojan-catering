<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\MenuItem;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        // Orders per day (last 7 days)
        $ordersPerDay = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total')
        )
        ->where('created_at', '>=', now()->subDays(7))
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // Revenue per day (last 7 days)
        $revenuePerDay = Order::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total_amount) as total')
        )
        ->where('created_at', '>=', now()->subDays(7))
        ->where('status', 'delivered')
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        // Top selling items
        $topItems = OrderItem::select(
            'menu_item_id',
            DB::raw('SUM(quantity) as total_sold')
        )
        ->with('menuItem')
        ->groupBy('menu_item_id')
        ->orderBy('total_sold', 'desc')
        ->take(5)
        ->get();

        // Orders by status
        $ordersByStatus = Order::select(
            'status',
            DB::raw('COUNT(*) as total')
        )
        ->groupBy('status')
        ->get();

        // Summary stats
        $totalRevenue   = Order::where('status', 'delivered')->sum('total_amount');
        $totalOrders    = Order::count();
        $totalCustomers = User::where('role', 'customer')->count();
        $avgOrderValue  = Order::where('status', 'delivered')->avg('total_amount') ?? 0;

        return view('admin.analytics', compact(
            'ordersPerDay',
            'revenuePerDay',
            'topItems',
            'ordersByStatus',
            'totalRevenue',
            'totalOrders',
            'totalCustomers',
            'avgOrderValue'
        ));
    }
}