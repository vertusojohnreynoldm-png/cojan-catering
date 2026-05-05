<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;

class DashboardController extends Controller
{
    public function index()
    {
        $deliveries = Delivery::where('user_id', auth()->id())
            ->with('order.user')
            ->orderBy('created_at', 'desc')
            ->get();

        $pendingDeliveries   = $deliveries->where('status', 'assigned')->count();
        $completedDeliveries = $deliveries->where('status', 'delivered')->count();

        return view('delivery.dashboard', compact(
            'deliveries',
            'pendingDeliveries',
            'completedDeliveries'
        ));
    }
}