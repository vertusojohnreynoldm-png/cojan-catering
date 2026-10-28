<?php

namespace App\Http\Controllers\Delivery;

use App\Events\DeliveryLocationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\DeliveryLocation;
use App\Services\OrderNotifier;
use App\Services\RouteService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show($id)
    {
        $delivery = Delivery::where('user_id', auth()->id())
            ->with('order.user', 'order.orderItems.menuItem', 'order.orderItems.package')
            ->findOrFail($id);

        // Navigation (map, route, Navigate/Waze, call) — menu orders only,
        // and only while the delivery is active. Otherwise the customer's
        // coordinates never reach the page at all.
        $nav = null;
        if ($delivery->navigationActive()) {
            $order = $delivery->order;
            $dest = DeliveryLocation::forOrder($order);
            $latLng = sprintf('%.7F,%.7F', $dest['lat'], $dest['lng']);
            $nav = $dest + [
                'google' => 'https://www.google.com/maps/dir/?api=1&destination=' . $latLng . '&travelmode=driving',
                'waze'   => 'https://waze.com/ul?ll=' . $latLng . '&navigate=yes',
                'tel'    => 'tel:' . preg_replace('/[^0-9+]/', '', (string) $order->delivery_phone),
            ];
        }

        return view('delivery.order-detail', compact('delivery', 'nav'));
    }

    /**
     * Road route from the rider's CURRENT position (sent by the assigned
     * rider's own page) to the order's pin. Rider-only, active menu
     * deliveries only, rate-limited (throttle:routing). The destination is
     * always taken from the order on the server — never from the request.
     */
    public function route(Request $request, $id)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $delivery = Delivery::where('user_id', auth()->id())->with('order')->findOrFail($id);

        if (!$delivery->navigationActive()) {
            return response()->json(['message' => 'Navigation is only available while this delivery is active.'], 403);
        }

        $dest = DeliveryLocation::forOrder($delivery->order);
        $route = RouteService::route([(float) $request->lat, (float) $request->lng], [$dest['lat'], $dest['lng']]);

        return response()->json($route + ['destination' => $dest]);
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
            // Readings worse than 5 km are IP/Wi-Fi guesses, not GPS.
            'accuracy' => 'nullable|numeric|min:0|max:5000',
            'heading'  => 'nullable|numeric|between:0,360',
            'speed'    => 'nullable|numeric|min:0|max:100',
        ]);

        $delivery = Delivery::where('user_id', auth()->id())->with('order')->findOrFail($id);

        // Finished, failed, cancelled — or a catering booking, which never
        // has live tracking: tell the rider's page to STOP sharing (it
        // clears its GPS watch and releases the screen wake lock).
        if (!$delivery->navigationActive()) {
            return response()->json([
                'success' => false,
                'stop'    => true,
                'message' => $delivery->order?->status === 'cancelled'
                    ? 'This order was cancelled — location sharing has stopped.'
                    : 'This delivery is finished — location sharing has stopped.',
            ], 409);
        }

        // Assigned but not picked up yet: not shared yet, but keep watching.
        if (!in_array($delivery->status, ['picked_up', 'in_transit'], true)) {
            return response()->json([
                'success' => false,
                'stop'    => false,
                'message' => 'Location sharing starts once you mark the order Picked Up.',
            ], 422);
        }

        $delivery->update([
            'current_lat'          => $request->lat,
            'current_lng'          => $request->lng,
            'current_accuracy'     => $request->accuracy,
            'current_heading'      => $request->heading,
            'current_speed'        => $request->speed,
            'last_location_update' => now(),
        ]);

        broadcast(new DeliveryLocationUpdated($delivery))->toOthers();

        return response()->json(['success' => true, 'stop' => false]);
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

        // Menu orders: a scan only works while you have the order (picked up
        // / in transit) — the same window the customer's QR is shown in.
        // Bookings are unchanged.
        if (!$order->isBooking()) {
            $order->setRelation('delivery', $delivery);
            $error = match ($order->qrStage()) {
                'waiting'   => "Too early — Order #{$order->order_number} hasn't been picked up yet. Open it from My Deliveries and mark it Picked Up first.",
                'delivered' => "Order #{$order->order_number} is already delivered — there's nothing left to scan.",
                'cancelled' => "Order #{$order->order_number} was cancelled — don't deliver it.",
                'failed'    => "Order #{$order->order_number} is marked as failed — there's nothing left to scan.",
                default     => null,
            };
            if ($error) {
                return redirect()->route('delivery.dashboard')->with('error', $error);
            }
        }

        return redirect()->route('delivery.orders.show', $delivery->id);
    }
}