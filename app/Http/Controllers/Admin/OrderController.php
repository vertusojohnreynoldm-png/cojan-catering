<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('user')->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();

        return view('admin.orders', compact('orders'));
    }

    public function show($id)
    {
        $order = Order::with('user', 'orderItems.menuItem', 'delivery')->findOrFail($id);

        return view('admin.order-detail', compact('order'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,out_for_delivery,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $request->status]);

        return redirect()->route('admin.orders.show', $id)
            ->with('success', 'Order status updated successfully!');
    }

    public function assignDelivery(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $order = Order::findOrFail($id);

        $order->delivery()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'user_id'     => $request->user_id,
                'status'      => 'assigned',
                'assigned_at' => now(),
            ]
        );

        $order->update(['status' => 'out_for_delivery']);

        return redirect()->route('admin.orders.show', $id)
            ->with('success', 'Delivery personnel assigned successfully!');
    }
}