<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\MenuItem;
use App\Services\BusinessHours;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);
        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));

        // Adding to cart is never blocked (see OrderController) — this is
        // purely a heads-up so a customer isn't surprised only at checkout.
        $hasMenuItems = collect($cart)->contains(fn($item) => ($item['type'] ?? 'menu') !== 'package');
        $orderingOpen = BusinessHours::isMenuOrderingOpen();

        return view('customer.cart', compact('cart', 'total', 'hasMenuItems', 'orderingOpen'));
    }

    public function add(Request $request)
    {
        $menuItem = MenuItem::findOrFail($request->menu_item_id);
        $cart = session()->get('cart', []);

        // Cart keys are namespaced ("menu_{id}" / "package_{id}") because menu
        // items and packages are separate id sequences — an unqualified id could
        // collide between the two and silently overwrite a cart entry.
        $key = 'menu_' . $menuItem->id;

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $request->quantity ?? 1;
        } else {
            $cart[$key] = [
                'type'     => 'menu',
                'id'       => $menuItem->id,
                'name'     => $menuItem->name,
                'price'    => $menuItem->price,
                'quantity' => $request->quantity ?? 1,
                'image'    => $menuItem->image,
            ];
        }

        session()->put('cart', $cart);

        return redirect()->route('customer.cart')->with('success', $menuItem->name . ' added to cart!');
    }

    public function addPackage(Request $request)
    {
        $package = CateringPackage::where('is_available', true)->findOrFail($request->package_id);
        $cart = session()->get('cart', []);

        $key = 'package_' . $package->id;

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] += $request->quantity ?? 1;
        } else {
            $cart[$key] = [
                'type'     => 'package',
                'id'       => $package->id,
                'name'     => $package->name,
                'price'    => $package->price,
                'pax'      => $package->pax,
                'quantity' => $request->quantity ?? 1,
            ];
        }

        session()->put('cart', $cart);

        return redirect()->route('customer.cart')->with('success', $package->name . ' added to cart!');
    }

    public function update(Request $request, $id)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            if ($request->quantity <= 0) {
                unset($cart[$id]);
            } else {
                $cart[$id]['quantity'] = $request->quantity;
            }
            session()->put('cart', $cart);
        }

        return redirect()->route('customer.cart')->with('success', 'Cart updated!');
    }

    public function remove($id)
    {
        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
            session()->put('cart', $cart);
        }

        return redirect()->route('customer.cart')->with('success', 'Item removed from cart!');
    }

    public function clear()
    {
        session()->forget('cart');

        return redirect()->route('customer.cart')->with('success', 'Cart cleared!');
    }
}