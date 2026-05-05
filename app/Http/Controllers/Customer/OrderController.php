<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('customer.orders', compact('orders'));
    }

    public function checkout()
    {
        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('customer.menu')->with('error', 'Your cart is empty!');
        }

        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));

        return view('customer.checkout', compact('cart', 'total'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'delivery_address' => 'required|string',
            'delivery_phone'   => 'required|string',
            'notes'            => 'nullable|string',
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('customer.menu')->with('error', 'Your cart is empty!');
        }

        $subtotal = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));
        $deliveryFee = 50;
        $total = $subtotal + $deliveryFee;

        $order = Order::create([
            'user_id'          => auth()->id(),
            'order_number'     => 'ORD-' . strtoupper(Str::random(8)),
            'status'           => 'pending',
            'payment_method'   => 'cash_on_delivery',
            'payment_status'   => 'unpaid',
            'subtotal'         => $subtotal,
            'delivery_fee'     => $deliveryFee,
            'total_amount'     => $total,
            'delivery_address' => $request->delivery_address,
            'delivery_phone'   => $request->delivery_phone,
            'notes'            => $request->notes,
        ]);

        foreach ($cart as $item) {
            OrderItem::create([
                'order_id'     => $order->id,
                'menu_item_id' => $item['id'],
                'quantity'     => $item['quantity'],
                'unit_price'   => $item['price'],
                'subtotal'     => $item['price'] * $item['quantity'],
            ]);
        }

        $qrCode = QrCode::size(300)->generate(
    route('customer.orders.track', $order->order_number)
);

$order->update(['qr_code' => $qrCode]);

        session()->forget('cart');

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Order placed successfully!');
    }

    public function show($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->with('orderItems.menuItem')
            ->findOrFail($id);

        return view('customer.order-detail', compact('order'));
    }

    public function track($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->with('orderItems.menuItem', 'delivery')
            ->firstOrFail();

        return view('customer.order-track', compact('order'));
    }
}