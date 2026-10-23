<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\BookingUnavailableException;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\CateringPackage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Rules\PhilippineMobileNumber;
use App\Rules\ValidPersonName;
use App\Services\BookingCapacity;
use App\Services\BookingSettings;
use App\Services\Downpayment;
use App\Services\OrderNotifier;
use App\Services\UnpaidBookingExpirer;
use App\Services\OrderSubmissionGuard;
use App\Services\SubmissionToken;
use App\Services\PackageStockService;
use App\Services\ServiceArea;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

        $packages = CateringPackage::where('is_available', true)
            ->with('menuItems', 'utensils')
            ->get();

        $cateringAreas = ServiceArea::cateringPsgcForDropdowns();

        // Feeds the Avail modal's calendar: the bookable window plus every
        // full/blocked date inside it, which the calendar greys out. Only a
        // hint — storeBooking() and store() re-check all of it server-side.
        $eventCalendar = [
            'minDate'     => BookingCapacity::earliestCateringDate()->toDateString(),
            'maxDate'     => BookingCapacity::latestCateringDate()->toDateString(),
            'unavailable' => BookingCapacity::unavailableCateringDates(),
            'leadDays'    => BookingSettings::get('catering_min_lead_days'),
        ];

        $pendingLimitError = BookingCapacity::pendingLimitError(auth()->id(), true);

        return view('customer.packages', compact('packages', 'cateringAreas', 'eventCalendar', 'pendingLimitError'));
    }

    // Step 1 of the Avail flow: the modal's booking details, staged in the
    // session (mirroring how the regular cart itself is session-based)
    // rather than creating anything yet.
    public function storeBooking(Request $request)
    {
        CateringPackage::where('is_available', true)->findOrFail($request->package_id);

        // Early, friendly stop — no point filling in event details for a
        // booking the final step would refuse anyway.
        if ($pendingError = BookingCapacity::pendingLimitError(auth()->id(), true)) {
            return redirect()->route('customer.packages.index')->with('error', $pendingError);
        }

        // The modal sends the calendar date and the time dropdown separately
        // (so the calendar can be a date-only picker); everything downstream
        // still works with the single combined event_date it always has.
        if ($request->filled('event_day') && $request->filled('event_time')) {
            $request->merge(['event_date' => $request->event_day . ' ' . $request->event_time]);
        }

        $validated = $request->validate([
            'package_id'     => 'required|exists:catering_packages,id',
            'contact_name'   => ['required', 'string', 'max:255', new ValidPersonName],
            'contact_number' => ['required', 'string', 'max:20', new PhilippineMobileNumber],
            'province'       => ['required', 'string'],
            'municipality'   => ['required', 'string'],
            'barangay'       => ['required', 'string'],
            'street'         => ['required', 'string', 'min:5'],
            'landmark'       => ['nullable', 'string', 'max:255'],
            'event_date'     => ['required', 'date', 'after:now'],
            'event_type'     => 'nullable|string|max:100',
        ], [
            'event_date.required' => 'Please choose an event date and time.',
            'event_date.after' => 'Please choose an event date and time in the future.',
            'street.min' => 'Please enter a more complete street/purok/house number.',
        ]);

        // Lead time, max advance, blocked dates, date capacity and "you
        // already have a booking that day" — checked early here for a
        // friendly error, and again under a row lock in store().
        $dateError = BookingCapacity::cateringDateError(Carbon::parse($validated['event_date']), auth()->id());
        if ($dateError) {
            return back()->withInput()->withErrors(['event_date' => $dateError]);
        }

        $areaError = ServiceArea::cateringDeliveryError($validated['province'], $validated['municipality'], $validated['barangay']);
        if ($areaError) {
            return back()->withInput()->withErrors(['barangay' => $areaError]);
        }

        $validated['contact_number'] = PhilippineMobileNumber::normalize($validated['contact_number']);

        // 'venue' is what checkout()/store() further down the Avail flow
        // read as the order's delivery_address — keeping that one combined
        // key means neither of those methods needs to know the address is
        // now structured at all.
        $validated['venue'] = ServiceArea::formatAddress(
            $validated['street'], $validated['barangay'], $validated['municipality'], $validated['province'], $validated['landmark'] ?? null
        );

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

        // The date may have filled up or been blocked since step 1.
        $dateError = BookingCapacity::cateringDateError(Carbon::parse($booking['event_date']), auth()->id());
        if ($dateError) {
            return redirect()->route('customer.packages.index')->with('error', $dateError);
        }

        $total = $package->price; // no delivery fee for package bookings

        // Shown up front so the customer knows exactly what they're agreeing
        // to; store() recalculates all three from the same rules.
        $downpayment = Downpayment::amountFor($total);
        $balance = round($total - $downpayment, 2);
        $paymentDeadline = Downpayment::deadlineFor(Carbon::parse($booking['event_date']));
        $downpaymentPercent = Downpayment::percent();

        $pendingLimitError = BookingCapacity::pendingLimitError(auth()->id(), true);
        $submissionToken = SubmissionToken::issue('package_checkout');

        return view('customer.package-checkout', compact(
            'package', 'booking', 'total', 'downpayment', 'balance', 'paymentDeadline', 'downpaymentPercent',
            'pendingLimitError', 'submissionToken'
        ));
    }

    // Step 3: confirm — creates the Order/OrderItem via the same stock-lock
    // logic the regular cart flow uses (PackageStockService), just scoped to
    // a single package rather than a whole cart.
    public function store(Request $request)
    {
        UnpaidBookingExpirer::runIfDue(); // lazy expiry — Railway has no scheduler

        // Duplicate submit, stale page, rate limit, pending limit — first,
        // so a double tap on "Confirm Booking" never books twice.
        if ($redirect = OrderSubmissionGuard::check($request, 'package_checkout', 'customer.packages.checkout', true)) {
            return $redirect;
        }

        $booking = session()->get('package_booking');

        if (!$booking) {
            return redirect()->route('customer.packages.index')
                ->with('error', 'Your booking session expired. Please start again.');
        }

        // No payment choice to validate any more: the downpayment is always
        // GCash (paid after this, proven with a receipt upload), and the
        // balance is settled on the event day.

        $package = CateringPackage::where('is_available', true)->with('utensils')->find($booking['package_id']);

        if (!$package) {
            session()->forget('package_booking');

            return redirect()->route('customer.packages.index')
                ->with('error', 'This package is no longer available.');
        }

        try {
            $order = DB::transaction(function () use ($request, $package, $booking) {
                // Authoritative date check, with this event date's row lock
                // held until commit — two customers racing for the last
                // booking on a date can't both get it.
                BookingCapacity::reserveCatering(Carbon::parse($booking['event_date']), auth()->id());
                BookingCapacity::reservePendingSlot(auth()->id(), true);

                $reservedUtensils = PackageStockService::lockAndDecrement([[$package, 1]]);

                $order = Order::create([
                    'user_id'          => auth()->id(),
                    'order_number'     => 'ORD-' . strtoupper(Str::random(8)),
                    'status'           => 'pending',
                    'payment_method'   => 'gcash', // the downpayment's method
                    'gcash_reference'  => null,    // given with the receipt upload
                    'payment_status'   => 'unpaid',
                    'downpayment_amount' => Downpayment::amountFor($package->price),
                    'payment_due_at'   => Downpayment::deadlineFor(Carbon::parse($booking['event_date'])),
                    'subtotal'         => $package->price,
                    'delivery_fee'     => 0,
                    'total_amount'     => $package->price,
                    'delivery_address' => $booking['venue'],
                    'province'         => $booking['province'],
                    'municipality'     => $booking['municipality'],
                    'barangay'         => $booking['barangay'],
                    'street'           => $booking['street'],
                    'landmark'         => $booking['landmark'] ?? null,
                    'delivery_phone'   => $booking['contact_number'],
                    'contact_name'     => $booking['contact_name'],
                    'event_date'       => $booking['event_date'],
                    'event_type'       => $booking['event_type'] ?? null,
                    'idempotency_key'  => $request->submission_token,
                    'reserved_utensils' => $reservedUtensils ?: null,
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
            }, attempts: 3); // retried on a MySQL deadlock — see BookingCapacity::lock()
        } catch (Throwable $e) {
            // If a simultaneous duplicate of this request (same token) already
            // booked it, show that booking — whether this one lost on the
            // unique idempotency_key or on the date/pending-limit re-check.
            if ($existing = SubmissionToken::existingOrder($request->submission_token, auth()->id())) {
                return redirect()->route('customer.orders.show', $existing->id)
                    ->with('success', 'Your booking was already placed — here it is.');
            }

            if ($e instanceof BookingUnavailableException) {
                return $e->field === 'pending_limit'
                    ? back()->withErrors(['pending_limit' => $e->getMessage()])
                    : redirect()->route('customer.packages.index')->with('error', $e->getMessage());
            }

            if ($e instanceof InsufficientStockException) {
                return redirect()->route('customer.packages.checkout')->with('error', $e->getMessage());
            }

            // Unexpected — the customer gets a generic message, so make sure
            // the real cause lands in the log.
            report($e);

            return redirect()->route('customer.packages.checkout')
                ->with('error', 'We could not complete your booking. Please try again.');
        }

        session()->forget('package_booking');
        SubmissionToken::forget('package_checkout', $request->submission_token);

        OrderNotifier::sendBookingPayment($order, 'received');

        return redirect()->route('customer.orders.show', $order->id)->with(
            'success',
            'Booking received! Pay the ₱' . number_format($order->downpayment_amount, 2)
                . ' downpayment via GCash and upload your receipt by '
                . $order->payment_due_at->format('M d, g:i A') . ' to secure your date.'
        );
    }
}
