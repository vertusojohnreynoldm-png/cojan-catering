<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\QrHandoff;
use Illuminate\Http\Request;

/**
 * /track/{order number} — the link inside every order QR. Logging in first
 * is required (guests go through login and land back here).
 *
 *   customer          → their tracking page (unchanged; 404 if not theirs)
 *   ASSIGNED rider    → "QR verified at hand-off", then the rider's order
 *                       page — menu orders, picked up / in transit only
 *   anyone else       → their own dashboard, told nothing about the order
 */
class TrackLinkController extends Controller
{
    public function __invoke(Request $request, string $orderNumber)
    {
        $user = $request->user();

        return match ($user->role) {
            'customer' => app(CustomerOrderController::class)->track($orderNumber),
            'delivery' => $this->riderHandoff($request, $orderNumber),
            'admin'    => redirect()->route('admin.dashboard'),
            default    => redirect()->route('customer.dashboard'),
        };
    }

    private function riderHandoff(Request $request, string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)->first();
        $delivery = $order
            ? Delivery::where('order_id', $order->id)->where('user_id', $request->user()->id)->first()
            : null;

        // Not this rider's order (or no such order), or a catering booking:
        // same as before this feature — just the rider's dashboard.
        if (!$delivery || $order->isBooking()) {
            return redirect()->route('delivery.dashboard');
        }

        $order->setRelation('delivery', $delivery);
        if ($error = $order->riderScanRefusal()) {
            return redirect()->route('delivery.dashboard')->with('error', $error);
        }

        QrHandoff::record($delivery, $request->user(), null, 'phone camera');

        return redirect()->route('delivery.orders.show', $delivery->id)->with('qr_verified', true);
    }
}
