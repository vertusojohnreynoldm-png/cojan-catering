<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PackageStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

// NOTE: package bookings deliberately have NO business-hours restriction —
// unlike regular menu ordering (see Customer\OrderController /
// App\Services\BusinessHours), a booking is for a future event, not
// same-day delivery, so a customer can book at any hour of the day or
// night. Do not copy-paste a BusinessHours check into this controller.
class PackageController extends Controller
{
    public function index()
    {
        $packages = CateringPackage::where('is_available', true)
            ->with('menuItems', 'utensils')
            ->get();

        return view('customer.packages', compact('packages'));
    }

    // Step 1 of the Avail flow: the modal's booking details, staged in the
    // session (mirroring how the regular cart itself is session-based)
    // rather than creating anything yet.
    public function storeBooking(Request $request)
    {
        CateringPackage::where('is_available', true)->findOrFail($request->package_id);

        $minEventDate = now()->addHours(24);

        $validated = $request->validate([
            'package_id'     => 'required|exists:catering_packages,id',
            'contact_name'   => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'venue'          => 'required|string',
            'event_date'     => ['required', 'date', 'after_or_equal:' . $minEventDate->toDateTimeString()],
            'event_type'     => 'nullable|string|max:100',
        ], [
            'event_date.after_or_equal' => 'Event date/time must be at least 24 hours from now, to allow prep time.',
        ]);

        session()->put('package_booking', $validated);

        return redirect()->route('customer.packages.checkout');
    }

    // Step 2: a checkout page scoped to just this one package booking — no
    // cart, no delivery fee, just the package, the event details just
    // collected, and payment method choice.
    public function checkout()
    {
        $booking = session()->get('package_booking');

        if (!$booking) {
            return redirect()->route('customer.packages.index')
                ->with('error', 'Please select a package and fill in your event details first.');
        }

        $package = CateringPackage::where('is_available', true)->find($booking['package_id']);

        if (!$package) {
            session()->forget('package_booking');

            return redirect()->route('customer.packages.index')
                ->with('error', 'This package is no longer available.');
        }

        $total = $package->price; // no delivery fee for package bookings

        $gcashQr = null;

        try {
            $gcashQr = QrCode::size(200)->generate(config('services.gcash.qr_payload'));
        } catch (Throwable $e) {
            // Leave $gcashQr null — the shared payment-method-selector component
            // falls back to a plain-text readout.
        }

        return view('customer.package-checkout', compact('package', 'booking', 'total', 'gcashQr'));
    }

    // Step 3: confirm — creates the Order/OrderItem via the same stock-lock
    // logic the regular cart flow uses (PackageStockService), just scoped to
    // a single package rather than a whole cart.
    public function store(Request $request)
    {
        $booking = session()->get('package_booking');

        if (!$booking) {
            return redirect()->route('customer.packages.index')
                ->with('error', 'Your booking session expired. Please start again.');
        }

        $request->validate([
            'payment_method'  => 'required|in:cash_on_delivery,gcash',
            'gcash_reference' => 'required_if:payment_method,gcash|nullable|string|max:100',
        ]);

        $package = CateringPackage::where('is_available', true)->with('utensils')->find($booking['package_id']);

        if (!$package) {
            session()->forget('package_booking');

            return redirect()->route('customer.packages.index')
                ->with('error', 'This package is no longer available.');
        }

        try {
            $order = DB::transaction(function () use ($request, $package, $booking) {
                PackageStockService::lockAndDecrement([[$package, 1]]);

                $order = Order::create([
                    'user_id'          => auth()->id(),
                    'order_number'     => 'ORD-' . strtoupper(Str::random(8)),
                    'status'           => 'pending',
                    'payment_method'   => $request->payment_method,
                    'gcash_reference'  => $request->payment_method === 'gcash' ? $request->gcash_reference : null,
                    'payment_status'   => 'unpaid',
                    'subtotal'         => $package->price,
                    'delivery_fee'     => 0,
                    'total_amount'     => $package->price,
                    'delivery_address' => $booking['venue'],
                    'delivery_phone'   => $booking['contact_number'],
                    'contact_name'     => $booking['contact_name'],
                    'event_date'       => $booking['event_date'],
                    'event_type'       => $booking['event_type'] ?? null,
                ]);

                OrderItem::create([
                    'order_id'   => $order->id,
                    'package_id' => $package->id,
                    'quantity'   => 1,
                    'unit_price' => $package->price,
                    'subtotal'   => $package->price,
                ]);

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
            return redirect()->route('customer.packages.checkout')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return redirect()->route('customer.packages.checkout')
                ->with('error', 'We could not complete your booking. Please try again.');
        }

        session()->forget('package_booking');

        return redirect()->route('customer.orders.show', $order->id)
            ->with('success', 'Package booked successfully!');
    }
}
