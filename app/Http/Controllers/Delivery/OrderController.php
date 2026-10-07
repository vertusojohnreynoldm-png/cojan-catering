<?php

namespace App\Http\Controllers\Delivery;

use App\Events\DeliveryLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show($id)
    {
        $delivery = Delivery::where('user_id', auth()->id())
            ->with('order.user', 'order.orderItems.menuItem', 'order.orderItems.package')
            ->findOrFail($id);

        return view('delivery.order-detail', compact('delivery'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:assigned,picked_up,in_transit,delivered,failed',
        ]);

        $delivery = Delivery::where('user_id', auth()->id())->findOrFail($id);
        
        $data = ['status' => $request->status];

        if ($request->status === 'picked_up') {
            $data['picked_up_at'] = now();
        } elseif ($request->status === 'delivered') {
            $data['delivered_at'] = now();
            $delivery->order->update([
                'status'         => 'delivered',
                'payment_status' => 'paid',
            ]);
        }

        $delivery->update($data);

        return redirect()->route('delivery.orders.show', $id)
            ->with('success', 'Delivery status updated successfully!');
    }

    public function updateLocation(Request $request, $id)
    {
        $request->validate([
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        $delivery = Delivery::where('user_id', auth()->id())->findOrFail($id);

        if (!in_array($delivery->status, ['picked_up', 'in_transit'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Location updates are only accepted while a delivery is out for delivery.',
            ], 422);
        }

        $delivery->update([
            'current_lat'          => $request->lat,
            'current_lng'          => $request->lng,
            'current_accuracy'     => $request->accuracy,
            'last_location_update' => now(),
        ]);

        broadcast(new DeliveryLocationUpdated($delivery))->toOthers();

        return response()->json(['success' => true]);
    }

    public function scan(Request $request)
    {
        $orderNumber = $request->order_number;
        $order       = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return redirect()->route('delivery.dashboard')
                ->with('error', 'Order not found!');
        }

        $delivery = Delivery::where('user_id', auth()->id())
            ->where('order_id', $order->id)
            ->first();

        if (!$delivery) {
            return redirect()->route('delivery.dashboard')
                ->with('error', 'This order is not assigned to you!');
        }

        return redirect()->route('delivery.orders.show', $delivery->id);
    }
}