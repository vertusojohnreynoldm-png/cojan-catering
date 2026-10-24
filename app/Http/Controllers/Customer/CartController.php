<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\MenuItem;
use App\Services\BusinessHours;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function index()
    {
        // Database prices × validated quantities; an older cart over the
        // per-item limit is capped here with a notice.
        [$cart, $cartNotices] = CartService::refresh();
        $total = CartService::subtotal($cart);

        // Adding to cart is never blocked (see OrderController) — this is
        // purely a heads-up so a customer isn't surprised only at checkout.
        $hasMenuItems = collect($cart)->contains(fn($item) => ($item['type'] ?? 'menu') !== 'package');
        $orderingOpen = BusinessHours::isMenuOrderingOpen();

        $maxQty = CartService::maxPerItem();

        return view('customer.cart', compact('cart', 'total', 'hasMenuItems', 'orderingOpen', 'cartNotices', 'maxQty'));
    }

    public function add(Request $request)
    {
        $max = CartService::maxPerItem();

        // A single request for more than the limit can only come from a
        // tampered form (the stepper stops at the limit) — rejected outright.
        $validator = Validator::make($request->all(), [
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity'     => ['nullable', 'integer', 'min:1', "max:{$max}"],
        ], [
            'quantity.max'     => CartService::maxMessage(),
            'quantity.min'     => 'Please choose a quantity of at least 1.',
            'quantity.integer' => 'Please enter a whole number.',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $menuItem = MenuItem::findOrFail($request->menu_item_id);
        $cart = session()->get('cart', []);
        $adding = (int) ($request->quantity ?? 1);

        // Cart keys are namespaced ("menu_{id}" / "package_{id}") because menu
        // items and packages are separate id sequences — an unqualified id could
        // collide between the two and silently overwrite a cart entry.
        $key = 'menu_' . $menuItem->id;
        $already = (int) ($cart[$key]['quantity'] ?? 0);
        $newQty = min($max, $already + $adding);

        // Price/name always from the database, never from the request.
        $cart[$key] = [
            'type'     => 'menu',
            'id'       => $menuItem->id,
            'name'     => $menuItem->name,
            'price'    => (float) $menuItem->price,
            'quantity' => $newQty,
            'image'    => $menuItem->image,
        ];

        session()->put('cart', $cart);

        if ($already + $adding > $max) {
            return redirect()->route('customer.cart')->with(
                'error',
                CartService::maxMessage() . " You now have {$max} × {$menuItem->name} in your cart."
            );
        }

        return redirect()->route('customer.cart')->with('success', $menuItem->name . ' added to cart!');
    }

    // Disabled: a package added through the cart had no event date, so it
    // bypassed every per-date capacity, lead-time and blocked-date check.
    // Every package now goes through the Avail flow (Customer\
    // PackageController). The route is kept, not deleted, so an old bookmark
    // or cached form lands somewhere helpful instead of a 404/405.
    public function addPackage(Request $request)
    {
        return redirect()->route('customer.packages.index')
            ->with('error', 'Catering packages are booked with an event date — tap "Avail" on the package you want.');
    }

    public function update(Request $request, $id)
    {
        $cart = session()->get('cart', []);

        if (!isset($cart[$id])) {
            return redirect()->route('customer.cart');
        }

        // 0 removes the line (the − button at 1). Menu items are capped at the
        // per-item limit; anything above it is a tampered request — rejected,
        // cart unchanged. Catering packages have no per-item limit.
        $isPackage = ($cart[$id]['type'] ?? 'menu') === 'package';
        $rules = ['required', 'integer', 'min:0'];
        if (!$isPackage) {
            $rules[] = 'max:' . CartService::maxPerItem();
        }

        $validator = Validator::make($request->all(), ['quantity' => $rules], [
            'quantity.max'      => CartService::maxMessage(),
            'quantity.required' => 'Please enter a quantity.',
            'quantity.integer'  => 'Please enter a whole number.',
            'quantity.min'      => 'Please enter a quantity of 0 or more.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('customer.cart')->with('error', $validator->errors()->first());
        }

        if ((int) $request->quantity === 0) {
            unset($cart[$id]);
        } else {
            $cart[$id]['quantity'] = (int) $request->quantity;
        }
        session()->put('cart', $cart);

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