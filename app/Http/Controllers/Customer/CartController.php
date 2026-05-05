<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $cart = session()->get('cart', []);
        $total = array_sum(array_map(fn($item) => $item['price'] * $item['quantity'], $cart));

        return view('customer.cart', compact('cart', 'total'));
    }

    public function add(Request $request)
    {
        $menuItem = MenuItem::findOrFail($request->menu_item_id);
        $cart = session()->get('cart', []);

        if (isset($cart[$menuItem->id])) {
            $cart[$menuItem->id]['quantity'] += $request->quantity ?? 1;
        } else {
            $cart[$menuItem->id] = [
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