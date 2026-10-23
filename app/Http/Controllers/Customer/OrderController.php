<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\BookingUnavailableException;
use App\Exceptions\DuplicateSlotOrderException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Services\UnpaidBookingExpirer;
use App\Models\CateringPackage;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ActivityLogger;
use App\Services\BookingCapacity;
use App\Services\DeliveryLocation;
use App\Services\OrderSubmissionGuard;
use App\Services\SubmissionToken;
use App\Services\BusinessHours;
use App\Services\DeliverySlots;
use App\Services\PackageStockService;
use App\Services\ServiceArea;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class OrderController extends Controller
{
    public function index()
    {
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

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

        $minDeliveryDate = DeliverySlots::earliestSelectableDate()->toDateString();
        $maxDeliveryDate = DeliverySlots::latestSelectableDate()->toDateString();

        // A cart with any menu item in it is locked to San Jose (a flat
        // barangay list); a package-only cart gets the full two-province
        // cascading dropdowns, same as the catering Avail form — see the
        // matching note in store().
        $sanJoseBarangays = ServiceArea::menuBarangays();
        $cateringAreas = $hasMenuItems ? [] : ServiceArea::cateringPsgcForDropdowns();

        // Admin-blocked days inside the 3-day scheduling window — listed
        // under the date input, since a native date picker can't grey them
        // out. If today itself is blocked, ASAP is disabled in the view.
        $blockedDeliveryDates = BookingCapacity::blockedDatesBetween(
            DeliverySlots::earliestSelectableDate(), DeliverySlots::latestSelectableDate()
        );
        $todayBlockedReason = $blockedDeliveryDates[now()->toDateString()] ?? null;

        // Shown up front so the customer isn't surprised on submit; store()
        // enforces it regardless.
        $pendingLimitError = BookingCapacity::pendingLimitError(auth()->id(), false);
        $submissionToken = SubmissionToken::issue('checkout');

        // Delivery-pin map (menu carts only): the San Jose box, barangay
        // centres from the static data file, and the customer's saved pin.
        $user = auth()->user();
        $pinMap = $hasMenuItems ? DeliveryLocation::checkoutMapConfig() + [
            'saved' => $user->default_latitude !== null
                ? [(float) $user->default_latitude, (float) $user->default_longitude]
                : null,
        ] : null;

        return view('customer.checkout', compact(
            'cart', 'total', 'gcashQr', 'minDeliveryDate', 'maxDeliveryDate',
            'hasMenuItems', 'sanJoseBarangays', 'cateringAreas',
            'blockedDeliveryDates', 'todayBlockedReason', 'pendingLimitError', 'submissionToken', 'pinMap'
        ));
    }

    // AJAX: populates the slot chips for whichever date the customer picks.
    // The exact same DeliverySlots call store() re-validates against, so
    // there's no risk of the options shown ever drifting from what's
    // actually accepted.
    public function deliverySlots(Request $request)
    {
        $request->validate(['date' => 'required|date_format:Y-m-d']);

        return response()->json(DeliverySlots::availabilityFor($request->date));
    }

    public function store(Request $request)
    {
        // Duplicate submit, stale page, rate limit, pending limit — before
        // anything else, so a double tap never even reaches validation.
        if ($redirect = OrderSubmissionGuard::check($request, 'checkout', 'customer.checkout', false)) {
            return $redirect;
        }

        $request->validate([
            'province'         => ['required', 'string'],
            'municipality'     => ['required', 'string'],
            'barangay'         => ['required', 'string'],
            'street'           => ['required', 'string', 'min:5'],
            'landmark'         => ['nullable', 'string', 'max:255'],
            'delivery_phone'   => ['required', 'string', new PhilippineMobileNumber],
            'notes'            => 'nullable|string',
            'payment_method'   => 'required|in:cash_on_delivery,gcash',
            'gcash_reference'  => 'required_if:payment_method,gcash|nullable|string|max:100',
            'delivery_type'    => 'required|in:asap,scheduled',
            'delivery_date'    => 'required_if:delivery_type,scheduled|nullable|date_format:Y-m-d',
            'delivery_slot'    => 'required_if:delivery_type,scheduled|nullable|string',
            'confirm_duplicate_slot' => 'nullable|boolean',
        ], [
            'street.min' => 'Please enter a more complete street/purok/house number.',
        ]);

        // The slot options the browser was shown are only ever generated by
        // DeliverySlots in the first place, but the browser is never trusted
        // for the actual value submitted — re-validate against the exact
        // same rules (business hours, lead time, 3-day window) here.
        if ($request->delivery_type === 'scheduled'
            && !DeliverySlots::isValidSlot($request->delivery_date, $request->delivery_slot)) {
            return back()->withInput()->withErrors([
                'delivery_slot' => 'That delivery window is no longer available — please pick another.',
            ]);
        }

        $requestedDeliveryAt = null;
        $deliveryWindowLabel = null;

        if ($request->delivery_type === 'scheduled') {
            $requestedDeliveryAt = \Illuminate\Support\Carbon::parse($request->delivery_slot);
            $deliveryWindowLabel = DeliverySlots::labelFor($request->delivery_slot);
        }

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

        // Map pin — required for any cart with menu items (the rider
        // navigates to it). A package-only cart is a catering-style order
        // and has no pin. The bounding box is a rough sanity check only;
        // the barangay rule just below stays the real service-area check.
        if ($hasMenuItems) {
            $request->validate([
                'latitude'      => ['required', 'numeric', 'between:-90,90'],
                'longitude'     => ['required', 'numeric', 'between:-180,180'],
                'save_location' => ['nullable', 'boolean'],
            ], [
                'latitude.required'  => 'Please set your delivery location on the map — drag the pin or tap "Use my current location".',
                'longitude.required' => 'Please set your delivery location on the map — drag the pin or tap "Use my current location".',
            ]);

            if (!DeliveryLocation::isInsideMenuArea((float) $request->latitude, (float) $request->longitude)) {
                return back()->withInput()->withErrors([
                    'latitude' => 'That map pin is outside San Jose, Occidental Mindoro — please move it to your delivery address.',
                ]);
            }
        }

        // Service-area gate — a mixed cart (menu items + packages together)
        // is still ONE order with ONE delivery address, so it can only go
        // out if that address satisfies the stricter of the two rules. A
        // cart with any menu item in it is held to the San-Jose-only rule
        // even if it also contains a package; a package-only cart gets the
        // wider two-province rule.
        $areaError = $hasMenuItems
            ? ServiceArea::menuDeliveryError($request->province, $request->municipality, $request->barangay)
            : ServiceArea::cateringDeliveryError($request->province, $request->municipality, $request->barangay);

        if ($areaError) {
            return back()->withInput()->withErrors(['barangay' => $areaError]);
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
            $order = DB::transaction(function () use (
                $request, $cart, $subtotal, $deliveryFee, $total, $requestedDeliveryAt, $deliveryWindowLabel, $hasMenuItems
            ) {
                // First, so a blocked day / full time frame aborts before any
                // stock is touched — and the slot's row lock is held until
                // this order commits, so a concurrent checkout can't also
                // take the last place in it.
                BookingCapacity::reserveMenuOrder(
                    $requestedDeliveryAt, auth()->id(), $request->boolean('confirm_duplicate_slot')
                );
                BookingCapacity::reservePendingSlot(auth()->id(), false);

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

                $reservedUtensils = PackageStockService::lockAndDecrement($packageLines);

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
                    'delivery_address' => ServiceArea::formatAddress(
                        $request->street, $request->barangay, $request->municipality, $request->province, $request->landmark
                    ),
                    'province'         => $request->province,
                    'municipality'     => $request->municipality,
                    'barangay'         => $request->barangay,
                    'street'           => $request->street,
                    'landmark'         => $request->landmark,
                    'delivery_phone'   => PhilippineMobileNumber::normalize($request->delivery_phone),
                    'notes'            => $request->notes,
                    'delivery_type'    => $request->delivery_type,
                    'requested_delivery_at'  => $requestedDeliveryAt,
                    'delivery_window_label'  => $deliveryWindowLabel,
                    'latitude'         => $hasMenuItems ? round((float) $request->latitude, 7) : null,
                    'longitude'        => $hasMenuItems ? round((float) $request->longitude, 7) : null,
                    'idempotency_key'  => $request->submission_token,
                    'reserved_utensils' => $reservedUtensils ?: null,
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
            }, attempts: 3); // retried on a MySQL deadlock — see BookingCapacity::lock()
        } catch (Throwable $e) {
            // Whatever stopped this request, if a simultaneous duplicate of
            // it (same token) already created the order, that's the answer —
            // e.g. it lost the race on the unique idempotency_key, or waited
            // on the slot lock and then found its own twin had taken the slot.
            if ($existing = SubmissionToken::existingOrder($request->submission_token, auth()->id())) {
                return redirect()->route('customer.orders.show', $existing->id)
                    ->with('success', 'Your order was already placed — here it is.');
            }

            if ($e instanceof DuplicateSlotOrderException) {
                return back()->withInput()
                    ->withErrors([$e->field => $e->getMessage()])
                    ->with('needs_duplicate_slot_confirmation', true);
            }

            if ($e instanceof BookingUnavailableException) {
                return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
            }

            if ($e instanceof InsufficientStockException) {
                return redirect()->route('customer.cart')->with('error', $e->getMessage());
            }

            // Unexpected — the customer gets a generic message, so make sure
            // the real cause lands in the log.
            report($e);

            return redirect()->route('customer.cart')
                ->with('error', 'We could not place your order. Please try again.');
        }

        session()->forget('cart');
        SubmissionToken::forget('checkout', $request->submission_token);

        // "Save as my default location" (on by default) — pre-fills the pin
        // next time. Quiet: a pin move isn't worth an activity-log entry.
        if ($hasMenuItems && $request->boolean('save_location')) {
            ActivityLogger::withoutLogging(fn () => $request->user()->update([
                'default_latitude'  => $order->latitude,
                'default_longitude' => $order->longitude,
            ]));
        }

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Order placed successfully!');
    }

    public function show($id)
    {
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

        $order = Order::where('user_id', auth()->id())
            ->with('orderItems.menuItem', 'orderItems.package', 'delivery')
            ->findOrFail($id);

        // The GCash QR is only needed while a downpayment is still due.
        $gcashQr = null;
        if ($order->canUploadReceipt()) {
            try {
                $gcashQr = QrCode::size(200)->generate(config('services.gcash.qr_payload'));
            } catch (Throwable $e) {
                // Leave null — the view shows the GCash number instead.
            }
        }

        return view('customer.order-detail', compact('order', 'gcashQr'));
    }

    public function track($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->where('user_id', auth()->id())
            ->with('orderItems.menuItem', 'orderItems.package', 'delivery')
            ->firstOrFail();

        return view('customer.order-track', compact('order'));
    }

    public function confirmReceived($id)
    {
        $order = Order::where('user_id', auth()->id())->with('delivery')->findOrFail($id);

        if ($order->status !== 'delivered' || !$order->delivery) {
            return back()->with('error', 'This order has not been marked as delivered yet.');
        }

        if (!$order->delivery->customer_confirmed_at) {
            $order->delivery->update(['customer_confirmed_at' => now()]);

            ActivityLogger::log(
                'order.customer_confirmed_received',
                'orders',
                "{$order->user->name} confirmed receipt of Order #{$order->order_number}",
                subject: $order,
                actor: auth()->user(),
            );
        }

        return back()->with('success', 'Thanks for confirming!');
    }
}
