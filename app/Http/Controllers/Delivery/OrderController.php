<?php

namespace App\Http\Controllers\Delivery;

use App\Events\DeliveryLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\OrderNotifier;
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
            // Never trust the browser: required server-side too, not just
            // via the disabled-until-photo-chosen submit button in the UI.
            'photo'          => 'required_if:status,delivered|nullable|image|max:5120',
            'received_by'    => 'nullable|string|max:255',
            'delivery_note'  => 'nullable|string|max:1000',
            'proof_lat'      => 'nullable|numeric|between:-90,90',
            'proof_lng'      => 'nullable|numeric|between:-180,180',
            'proof_accuracy' => 'nullable|numeric|min:0',
        ]);

        $delivery = Delivery::where('user_id', auth()->id())->findOrFail($id);

        $data = ['status' => $request->status];

        if ($request->status === 'picked_up') {
            $data['picked_up_at'] = now();
        } elseif ($request->status === 'delivered') {
            $data['delivered_at'] = now();
            $data['proof_photo_path'] = $request->file('photo')->store('delivery-proofs', 'public');
            $data['received_by'] = $request->received_by;
            $data['delivery_note'] = $request->delivery_note;
            $data['proof_lat'] = $request->proof_lat;
            $data['proof_lng'] = $request->proof_lng;
            $data['proof_accuracy'] = $request->proof_accuracy;

            $order = $delivery->order;
            $oldOrderStatus = $order->status;

            $order->update([
                'status'         => 'delivered',
                'payment_status' => 'paid',
            ]);

            // Only the 'delivered' transition ever changes the Order's own
            // status from this controller — picked_up/in_transit/assigned/
            // failed are tracked on the Delivery sub-model only, so there's
            // nothing order-status-wise to email about for those.
            if ($oldOrderStatus !== $order->status) {
                OrderNotifier::sendStatusChanged($order);
            }
        }

        $delivery->update($data);

        // The "Mark as Delivered" flow submits via fetch() (not a plain form
        // POST) specifically so a failed upload can be retried without
        // losing the already-compressed photo/preview/typed fields — a
        // normal form resubmit can't repopulate a file input at all. JSON in,
        // JSON out; the plain status dropdown still gets its usual redirect.
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'redirect' => route('delivery.orders.show', $id)]);
        }

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