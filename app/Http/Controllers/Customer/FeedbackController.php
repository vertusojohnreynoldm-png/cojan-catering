<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Order;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function create($orderId)
    {
        $order = Order::where('user_id', auth()->id())
            ->where('status', 'delivered')
            ->findOrFail($orderId);

        $existingFeedback = Feedback::where('order_id', $orderId)
            ->where('user_id', auth()->id())
            ->first();

        if ($existingFeedback) {
            return redirect()->route('customer.orders.show', $orderId)
                ->with('error', 'You have already submitted feedback for this order!');
        }

        return view('customer.feedback', compact('order'));
    }

    public function store(Request $request, $orderId)
    {
        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $order = Order::where('user_id', auth()->id())
            ->where('status', 'delivered')
            ->findOrFail($orderId);

        Feedback::create([
            'user_id'  => auth()->id(),
            'order_id' => $order->id,
            'rating'   => $request->rating,
            'comment'  => $request->comment,
        ]);

        return redirect()->route('customer.orders.show', $orderId)
            ->with('success', 'Thank you for your feedback!');
    }
}