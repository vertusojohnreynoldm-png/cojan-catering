<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\BusinessHours;
use App\Services\PackageStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

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

        // Packages are exempt — only a cart containing at least one regular
        // menu item is subject to business hours. Re-checked in store() too,
        // since reaching this page isn't the only way to submit an order.
        $hasMenuItems = collect($cart)->contains(fn($item) => ($item['type'] ?? 'menu') !== 'package');

        if ($hasMenuItems && !BusinessHours::isMenuOrderingOpen()) {
            return redirect()->route('customer.cart')->with(
                'error',
                "We're currently closed — online ordering for menu items is available "
                    . BusinessHours::hoursLabel() . ' daily. Catering packages can still be booked anytime.'
            );
        }

        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));

        // Static QR Ph / InstaPay payload — this is a real, account-linked
        // scannable payment code, so it's encoded byte-for-byte as-is. It
        // can't carry a dynamic amount (no merchant API), which is why the
        // amount is shown as separate text in the view instead.
        $gcashQr = null;

        try {
            $gcashQr = QrCode::size(200)->generate(config('services.gcash.qr_payload'));
        } catch (Throwable $e) {
            // Leave $gcashQr null — the view falls back to a plain-text readout.
        }

        return view('customer.checkout', compact('cart', 'total', 'gcashQr'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'delivery_address' => 'required|string',
            'delivery_phone'   => 'required|string',
            'notes'            => 'nullable|string',
            'payment_method'   => 'required|in:cash_on_delivery,gcash',
            'gcash_reference'  => 'required_if:payment_method,gcash|nullable|string|max:100',
        ]);

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('customer.menu')->with('error', 'Your cart is empty!');
        }

        // Authoritative check — this is the actual order-creation point, so
        // this is what must hold even if someone bypasses the disabled UI or
        // posts here directly. Packages are still exempt.
        $hasMenuItems = collect($cart)->contains(fn($item) => ($item['type'] ?? 'menu') !== 'package');

        if ($hasMenuItems && !BusinessHours::isMenuOrderingOpen()) {
            return redirect()->route('customer.cart')->with(
                'error',
                "We're currently closed — online ordering for menu items is available "
                    . BusinessHours::hoursLabel() . ' daily. Catering packages can still be booked anytime.'
            );
        }

        // Cart entries are either regular menu items or catering packages
        // (see CartController) — split them so each type can be re-validated
        // against its own live table.
        $menuCartIds = [];
        $packageCartIds = [];

        foreach ($cart as $key => $item) {
            if (($item['type'] ?? 'menu') === 'package') {
                $packageCartIds[$key] = $item['id'];
            } else {
                $menuCartIds[$key] = $item['id'];
            }
        }

        // Re-validate against the live menu/packages — either may have been
        // deleted or marked unavailable since being added to the (session-only)
        // cart.
        $menuItems = MenuItem::whereIn('id', array_values($menuCartIds))->get()->keyBy('id');
        $packages = CateringPackage::whereIn('id', array_values($packageCartIds))->get()->keyBy('id');
        $unavailableNames = [];

        foreach ($menuCartIds as $key => $id) {
            $menuItem = $menuItems->get($id);

            if (!$menuItem || !$menuItem->is_available) {
                $unavailableNames[] = $cart[$key]['name'];
                unset($cart[$key]);
            }
        }

        foreach ($packageCartIds as $key => $id) {
            $package = $packages->get($id);

            if (!$package || !$package->is_available) {
                $unavailableNames[] = $cart[$key]['name'];
                unset($cart[$key]);
            }
        }

        if (!empty($unavailableNames)) {
            session()->put('cart', $cart);

            return redirect()->route('customer.cart')->with(
                'error',
                'These items are no longer available and were removed from your cart: '
                    . implode(', ', $unavailableNames)
            );
        }

        $subtotal = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));
        $deliveryFee = 50;
        $total = $subtotal + $deliveryFee;

        try {
            $order = DB::transaction(function () use ($request, $cart, $subtotal, $deliveryFee, $total) {
                // Aggregate utensil requirements across every package in the
                // cart BEFORE creating anything, so a shortfall aborts the
                // whole order cleanly instead of leaving a partial commit.
                $packageLines = [];

                foreach ($cart as $item) {
                    if (($item['type'] ?? 'menu') !== 'package') {
                        continue;
                    }

                    $package = CateringPackage::with('utensils')->find($item['id']);
                    $packageLines[] = [$package, $item['quantity']];
                }

                PackageStockService::lockAndDecrement($packageLines);

                $order = Order::create([
                    'user_id'          => auth()->id(),
                    'order_number'     => 'ORD-' . strtoupper(Str::random(8)),
                    'status'           => 'pending',
                    'payment_method'   => $request->payment_method,
                    'gcash_reference'  => $request->payment_method === 'gcash' ? $request->gcash_reference : null,
                    'payment_status'   => 'unpaid',
                    'subtotal'         => $subtotal,
                    'delivery_fee'     => $deliveryFee,
                    'total_amount'     => $total,
                    'delivery_address' => $request->delivery_address,
                    'delivery_phone'   => $request->delivery_phone,
                    'notes'            => $request->notes,
                ]);

                foreach ($cart as $item) {
                    $isPackage = ($item['type'] ?? 'menu') === 'package';

                    OrderItem::create([
                        'order_id'     => $order->id,
                        'menu_item_id' => $isPackage ? null : $item['id'],
                        'package_id'   => $isPackage ? $item['id'] : null,
                        'quantity'     => $item['quantity'],
                        'unit_price'   => $item['price'],
                        'subtotal'     => $item['price'] * $item['quantity'],
                    ]);
                }

                try {
                    $qrCode = QrCode::size(300)->generate(
                        route('customer.orders.track', $order->order_number)
                    );
                } catch (Throwable $e) {
                    throw new \RuntimeException('QR code generation failed: ' . $e->getMessage(), 0, $e);
                }

                $order->update(['qr_code' => $qrCode]);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return redirect()->route('customer.cart')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->route('customer.cart')
                ->with('error', 'We could not place your order. Please try again.');
        }

        session()->forget('cart');

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Order placed successfully!');
    }

    public function show($id)
    {
        $order = Order::where('user_id', auth()->id())
            ->with('orderItems.menuItem', 'orderItems.package')
            ->findOrFail($id);

        return view('customer.order-detail', compact('order'));
    }

    public function track($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->with('orderItems.menuItem', 'orderItems.package', 'delivery')
            ->firstOrFail();

        return view('customer.order-track', compact('order'));
    }
}