<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnpaidBookingExpirer;
use App\Models\Order;
use App\Models\User;
use App\Exceptions\InsufficientStockException;
use App\Services\ActivityLogger;
use App\Services\CustomerBlocking;
use App\Services\Downpayment;
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
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

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

    /**
     * Two steps for a catering booking with a downpayment: the first call
     * confirms the GCash downpayment (payment_status stays 'unpaid' — the
     * balance is still due on the event day); the next marks the whole
     * thing paid. Every other order is marked paid in one step, as before.
     */
    public function markPaymentPaid($id)
    {
        $order = Order::with('user')->findOrFail($id);

        if ($order->status === 'cancelled') {
            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'This order is cancelled — there\'s no payment to record.');
        }

        if ($order->requiresDownpayment() && $order->downpayment_paid_at === null) {
            ActivityLogger::withoutLogging(fn () => $order->update(['downpayment_paid_at' => now()]));

            ActivityLogger::log(
                'booking.downpayment_confirmed',
                'orders',
                auth()->user()->name . " confirmed the ₱" . number_format($order->downpayment_amount, 2)
                    . " downpayment for Booking #{$order->order_number}",
                subject: $order,
                changes: ['downpayment_paid_at' => ['old' => null, 'new' => $order->downpayment_paid_at->toDateTimeString()]],
            );
            OrderNotifier::sendBookingPayment($order, 'confirmed');

            return redirect()->route('admin.orders.show', $id)->with(
                'success',
                'Downpayment confirmed. Balance of ₱' . number_format($order->balanceAmount(), 2) . ' is due on the event day.'
            );
        }

        $order->update(['payment_status' => 'paid']);

        return redirect()->route('admin.orders.show', $id)
            ->with('success', 'Payment marked as paid!');
    }

    /** The receipt doesn't check out — the customer is asked to upload again. */
    public function rejectReceipt(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ], [
            'reason.required' => 'Tell the customer what was wrong so they can fix it.',
        ]);

        $order = Order::with('user')->findOrFail($id);

        if (!$order->hasReceiptUnderReview()) {
            return redirect()->route('admin.orders.show', $id)
                ->with('error', 'There\'s no receipt waiting for review on this booking.');
        }

        // While a receipt is under review the booking can't expire, so the
        // deadline may already be behind us — give the customer a fresh
        // window to re-upload (never past the event start).
        $newDeadline = Downpayment::deadlineFor($order->event_date);
        $deadline = $order->payment_due_at->gt($newDeadline) ? $order->payment_due_at : $newDeadline;

        ActivityLogger::withoutLogging(fn () => $order->update([
            'receipt_rejected_at'      => now(),
            'receipt_rejection_reason' => trim($validated['reason']),
            'payment_due_at'           => $deadline,
        ]));

        ActivityLogger::log(
            'booking.receipt_rejected',
            'orders',
            auth()->user()->name . " rejected the GCash receipt for Booking #{$order->order_number} — " . trim($validated['reason']),
            subject: $order,
        );
        OrderNotifier::sendBookingPayment($order, 'rejected');

        return redirect()->route('admin.orders.show', $id)->with(
            'success',
            'Receipt rejected. The customer was emailed and can upload a new one until ' . $deadline->format('M d, g:i A') . '.'
        );
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
