<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Exceptions\InsufficientStockException;
use App\Services\CustomerBlocking;
use App\Services\OrderCancellation;
use App\Services\OrderNotifier;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        // Catering Bookings (the Avail flow) have their own dedicated list —
        // see Admin\BookingController — so this stays scoped to regular
        // menu-item orders only.
        $query = Order::regularOrders()->with('user', 'delivery')->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->get();

        return view('admin.orders', compact('orders'));
    }

    public function show($id)
    {
        $order = Order::with('user', 'orderItems.menuItem', 'orderItems.package', 'delivery')->findOrFail($id);
        $deliveryPersonnel = User::where('role', 'delivery')->get();

        return view('admin.order-detail', compact('order', 'deliveryPersonnel'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,out_for_delivery,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $oldStatus = $order->status;

        if ($oldStatus === 'cancelled' && $request->status !== 'cancelled') {
            // Reopening: its utensils (already returned to stock) have to be
            // reserved again, and it stops being flagged No-show/Fake.
            try {
                OrderCancellation::reopen($order, $request->status);
            } catch (InsufficientStockException $e) {
                return redirect()->route('admin.orders.show', $id)->with(
                    'error',
                    "Can't reopen this order — its utensils are no longer in stock. " . $e->getMessage()
                );
            }
        } else {
            // Cancelling returns utensil stock automatically (OrderObserver).
            $order->update(['status' => $request->status]);
        }

        if ($oldStatus !== $order->status) {
            OrderNotifier::sendStatusChanged($order);
        }

        return redirect()->route('admin.orders.show', $id)
            ->with('success', 'Order status updated successfully!');
    }

    /** No-show / Fake: cancel + flag, optionally blocking the customer too. */
    public function flag(Request $request, $id)
    {
        $validated = $request->validate([
            'flag'           => 'required|in:' . implode(',', array_keys(Order::FLAGS)),
            'reason'         => 'nullable|string|max:255',
            'block_customer' => 'nullable|boolean',
        ]);

        $order = Order::with('user')->findOrFail($id);

        if (!$order->isActive()) {
            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'Only an order that is still active can be marked as a No-show or Fake.');
        }

        $reason = isset($validated['reason']) ? trim($validated['reason']) : null;
        OrderCancellation::flag($order, $validated['flag'], $reason ?: null);
        OrderNotifier::sendStatusChanged($order);

        $message = ($order->isBooking() ? 'Booking' : 'Order') . ' marked as ' . Order::FLAGS[$validated['flag']] . ' and cancelled.';

        if ($request->boolean('block_customer') && $order->user && $order->user->isCustomer() && !$order->user->isBlocked()) {
            CustomerBlocking::block(
                $order->user,
                Order::FLAGS[$validated['flag']] . " on #{$order->order_number}" . ($reason ? " — {$reason}" : '')
            );
            $message .= " {$order->user->name} can no longer place orders.";
        }

        return redirect()->route('admin.orders.show', $id)->with('success', $message);
    }

    public function markPaymentPaid($id)
    {
        $order = Order::findOrFail($id);
        $order->update(['payment_status' => 'paid']);

        return redirect()->route('admin.orders.show', $id)
            ->with('success', 'Payment marked as paid!');
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