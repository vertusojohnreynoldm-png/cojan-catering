<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail - Cojan Catering Admin</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    @if($deliveryPin)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endif
</head>
<body>
<x-admin-nav />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">{{ $order->isBooking() ? '📦 Booking Detail' : 'Order Detail' }}</h1>
    <p class="cj-page-sub">Order #{{ $order->order_number }}</p>

    @if($order->event_date)
    <!-- Event Booking Details — package bookings only -->
    <div class="cj-card mb-3">
        <div class="cj-card-header amber">📦 Event Booking Details</div>
        <div class="cj-card-body">
            <div class="row g-2">
                <div class="col-12 col-md-4">
                    <p class="mb-0"><strong>Event Date &amp; Time:</strong><br>{{ $order->event_date->format('M d, Y \a\t h:i A') }}</p>
                </div>
                <div class="col-12 col-md-4">
                    <p class="mb-0">
                        <strong>Venue:</strong>
                        @if($order->barangay)
                            <span class="badge-cj badge-booking-type">{{ $order->barangay }}</span>
                        @endif
                        <br>{{ $order->delivery_address }}
                    </p>
                </div>
                <div class="col-12 col-md-4">
                    <p class="mb-0">
                        <strong>Booking Contact:</strong><br>
                        {{ $order->contact_name }} · {{ $order->delivery_phone }}
                        @if($order->event_type)
                            <br><span class="badge-cj badge-pending">{{ $order->event_type }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($order->delivery && $order->delivery->status === 'delivered')
    <!-- Delivery Proof -->
    <div class="cj-card mb-3">
        <div class="cj-card-header">📦 Delivery Proof</div>
        <div class="cj-card-body">
            <div class="row g-3">
                @if($order->delivery->proof_photo_path)
                    <div class="col-12 col-sm-5">
                        <img src="{{ route('orders.proof-photo', $order->id) }}" alt="Proof of delivery photo"
                             style="width:100%;border-radius:var(--radius-base);box-shadow:var(--shadow-sm);display:block;">
                    </div>
                @endif
                <div class="col-12 {{ $order->delivery->proof_photo_path ? 'col-sm-7' : '' }}">
                    <p class="mb-1"><strong>Delivered:</strong> {{ $order->delivery->delivered_at?->format('M d, Y h:i A') }}</p>
                    @if($order->delivery->received_by)
                        <p class="mb-1"><strong>Received by:</strong> {{ $order->delivery->received_by }}</p>
                    @endif
                    @if($order->delivery->delivery_note)
                        <p class="mb-1"><strong>Note:</strong> {{ $order->delivery->delivery_note }}</p>
                    @endif
                    @if($order->delivery->proof_lat && $order->delivery->proof_lng)
                        <p class="mb-1">
                            <strong>GPS at delivery:</strong>
                            {{ number_format($order->delivery->proof_lat, 5) }}, {{ number_format($order->delivery->proof_lng, 5) }}
                            @if($order->delivery->proof_accuracy)
                                (±{{ round($order->delivery->proof_accuracy) }}m)
                            @endif
                        </p>
                    @endif
                    @if($order->delivery->customer_confirmed_at)
                        <p class="mb-0" style="color:var(--green-dark);font-weight:600;">
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            Customer confirmed receipt {{ $order->delivery->customer_confirmed_at->format('M d, Y h:i A') }}
                        </p>
                    @else
                        <p class="mb-0" style="color:var(--text-light);">Customer has not confirmed receipt yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="row g-3">
        <!-- Order Info -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Order Information</div>
                <div class="cj-card-body">
                    <p>
                        <strong>Customer:</strong> {{ $order->user->name }}
                        @if($order->user->isBlocked())
                            <span class="badge-cj badge-blocked" title="{{ $order->user->blocked_reason }}"><i class="bi bi-slash-circle"></i> Blocked</span>
                        @endif
                    </p>
                    <p><strong>Email:</strong> {{ $order->user->email }}</p>
                    <p><strong>Phone:</strong> {{ $order->delivery_phone }}</p>
                    <p>
                        <strong>Address:</strong>
                        @if($order->barangay)
                            <span class="badge-cj badge-booking-type">{{ $order->barangay }}</span>
                        @endif
                        {{ $order->delivery_address }}
                    </p>
                    @if($deliveryPin)
                        {{-- Menu orders only: the customer's pin (admins can always see it). --}}
                        <div class="cj-admin-pin mb-3">
                            <div id="admin-pin-map" class="cj-admin-pin-map" role="img"
                                 aria-label="Customer delivery location on the map"></div>
                            <p class="mb-0 mt-1" style="font-size:var(--fs-xs);">
                                <a href="https://www.google.com/maps/search/?api=1&query={{ sprintf('%.7F,%.7F', $deliveryPin['lat'], $deliveryPin['lng']) }}"
                                   target="_blank" rel="noopener">Open in Google Maps <i class="bi bi-box-arrow-up-right"></i></a>
                                @if($deliveryPin['approximate'])
                                    · <span style="color:var(--status-amber-text);">Location approximate (no pin — barangay centre)</span>
                                @endif
                            </p>
                        </div>
                    @endif
                    @unless($order->isBooking())
                        <p>
                            <strong>Delivery Time:</strong> {{ $order->deliveryTimeLabel() }}
                            @if($order->isDeliveryDueSoon())
                                <span class="badge-cj badge-pending">Due soon</span>
                            @endif
                        </p>
                    @endunless
                    <p><strong>Date:</strong> {{ $order->created_at->format('M d, Y h:i A') }}</p>
                    @if($order->notes)
                        <p><strong>Notes:</strong> {{ $order->notes }}</p>
                    @endif
                    <hr class="divider">
                    @if($order->requiresDownpayment())
                        @include('admin.partials.booking-payment')
                    @else
                    <p><strong>Payment Method:</strong> {{ $order->payment_method === 'gcash' ? '📱 GCash' : '💵 Cash on Delivery' }}</p>
                    @if($order->payment_method === 'gcash' && $order->gcash_reference)
                        <p><strong>GCash Reference:</strong> <code>{{ $order->gcash_reference }}</code></p>
                    @endif
                    <p>
                        <strong>Payment Status:</strong>
                        <span class="badge-cj {{ $order->payment_status == 'paid' ? 'badge-paid' : 'badge-unpaid' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </p>
                    @if($order->payment_status !== 'paid' && $order->status !== 'cancelled')
                        <form method="POST" action="{{ route('admin.orders.mark-paid', $order->id) }}">
                            @csrf
                            <button type="submit" class="btn-cj-amber btn-cj btn-cj-sm">Mark as Paid</button>
                        </form>
                    @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Update Status -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Update Status</div>
                <div class="cj-card-body">
                    <p><strong>Current Status:</strong>
                        <x-order-status-badge :order="$order" show-flag />
                    </p>

                    @if($order->flagLabel())
                        <div class="alert-cj alert-danger" style="align-items:flex-start;font-size:var(--fs-sm);">
                            <i class="bi bi-flag-fill" style="margin-top:2px;"></i>
                            <div style="overflow-wrap:anywhere;">
                                Marked <strong>{{ $order->flagLabel() }}</strong>
                                {{ $order->flagged_at ? 'on ' . $order->flagged_at->format('M d, Y h:i A') : '' }}.
                                @if($order->cancellation_reason)
                                    <br>Reason: {{ $order->cancellation_reason }}
                                @endif
                            </div>
                        </div>
                    @endif
                    @if($order->status === 'cancelled' && $order->stock_restored_at)
                        <p style="font-size:var(--fs-xs);color:var(--text-light);">
                            <i class="bi bi-box-arrow-in-left"></i> Utensils returned to stock {{ $order->stock_restored_at->format('M d, Y h:i A') }}.
                        </p>
                    @endif

                    @if($order->isBooking())
                        {{-- Bookings get a reduced, booking-appropriate set of
                             actions — no 'preparing'/'out_for_delivery', no
                             delivery-personnel assignment, since there's no
                             delivery being tracked mid-route for an event. --}}
                        @if($order->isAwaitingDownpayment())
                            <p style="font-size:var(--fs-sm);color:var(--text-mid);margin-bottom:0;">
                                <i class="bi bi-hourglass-split"></i>
                                Waiting for the ₱{{ number_format($order->downpayment_amount, 2) }} GCash downpayment.
                                Confirm it in the Payment section first — then you can confirm the booking.
                            </p>
                        @elseif($order->status === 'pending')
                            <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn-cj-amber btn-cj">✓ Confirm Booking</button>
                            </form>
                        @elseif($order->status === 'confirmed')
                            <div class="d-flex gap-2 flex-wrap">
                                <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                                    @csrf
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="btn-cj btn-cj">✓ Mark Completed</button>
                                </form>
                                <form method="POST" action="{{ route('admin.orders.status', $order->id) }}"
                                      onsubmit="return confirm('Cancel this booking?');">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn-cj-danger btn-cj">✕ Cancel Booking</button>
                                </form>
                            </div>
                        @else
                            <p style="color:var(--text-light);font-size:.88rem;margin-bottom:0;">
                                This booking is {{ strtolower($order->statusLabel()) }} — no further actions available.
                            </p>
                        @endif
                    @else
                        <form method="POST" action="{{ route('admin.orders.status', $order->id) }}">
                            @csrf
                            <div class="d-flex gap-2">
                                <select name="status" class="cj-select">
                                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="confirmed" {{ $order->status == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Preparing</option>
                                    <option value="out_for_delivery" {{ $order->status == 'out_for_delivery' ? 'selected' : '' }}>Out for Delivery</option>
                                    <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                                <button type="submit" class="btn-cj btn-cj">Update</button>
                            </div>
                        </form>

                        <!-- Assign Delivery -->
                        @if($deliveryPersonnel->count() > 0)
                            <hr class="divider">
                            <p><strong>Assign Delivery Personnel:</strong></p>
                            <form method="POST" action="{{ route('admin.orders.assign', $order->id) }}">
                                @csrf
                                <div class="d-flex gap-2">
                                    <select name="user_id" class="cj-select">
                                        @foreach($deliveryPersonnel as $personnel)
                                            <option value="{{ $personnel->id }}"
                                                {{ $order->delivery && $order->delivery->user_id == $personnel->id ? 'selected' : '' }}>
                                                {{ $personnel->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn-cj-amber btn-cj">Assign</button>
                                </div>
                            </form>
                        @endif
                    @endif

                    @if($order->isActive())
                        <hr class="divider">
                        <p style="font-size:var(--fs-sm);margin-bottom:.5rem;"><strong>Problem with this {{ $order->isBooking() ? 'booking' : 'order' }}?</strong></p>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn-cj btn-cj-sm cj-flag-btn" data-bs-toggle="modal" data-bs-target="#flagModal" data-flag="no_show"
                                    style="background:transparent;color:var(--status-red-text);border-color:var(--status-red-text);">
                                <i class="bi bi-person-x"></i> Mark No-show
                            </button>
                            <button type="button" class="btn-cj btn-cj-sm cj-flag-btn" data-bs-toggle="modal" data-bs-target="#flagModal" data-flag="fake"
                                    style="background:transparent;color:var(--status-red-text);border-color:var(--status-red-text);">
                                <i class="bi bi-flag"></i> Mark Fake
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code -->
    <div class="cj-card mt-3">
        <div class="cj-card-header">QR Code</div>
        <div class="cj-card-body text-center py-4">
            @if($order->qr_code)
                <div style="display:inline-block;padding:16px;background:#fff;
                            border-radius:var(--radius-md);box-shadow:0 2px 12px rgba(0,0,0,0.08);">
                    {!! $order->qr_code !!}
                </div>
                <p style="color:var(--text-light);font-size:.8rem;margin-top:1rem;">Scan to track this order</p>
            @else
                <p style="color:var(--text-light);">QR Code not available</p>
            @endif
        </div>
    </div>

    <!-- Order Items -->
    <div class="cj-card mt-3">
        <div class="cj-card-header">Order Items</div>
        <div class="cj-card-body p-0">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order->orderItems as $item)
                            <tr>
                                <td>{{ $item->displayName() }}</td>
                                <td>₱{{ number_format($item->unit_price, 2) }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                            <td><strong>₱{{ number_format($order->subtotal, 2) }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Delivery Fee:</strong></td>
                            <td><strong>₱{{ number_format($order->delivery_fee, 2) }}</strong></td>
                        </tr>
                        <tr style="background:#f4f9f6;">
                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                            <td><strong style="color:var(--green-dark);">₱{{ number_format($order->total_amount, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <a href="{{ $order->isBooking() ? route('admin.bookings') : route('admin.orders') }}" class="cj-back-link">
        ← Back to {{ $order->isBooking() ? 'Bookings' : 'Orders' }}
    </a>
</div>

@if($order->isActive())
<!-- No-show / Fake Modal -->
<div class="modal fade" id="flagModal" tabindex="-1" aria-labelledby="flagModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="flagModalTitle">Flag {{ $order->isBooking() ? 'Booking' : 'Order' }} #{{ $order->order_number }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.orders.flag', $order->id) }}">
                @csrf
                <div class="modal-body">
                    <p style="font-size:var(--fs-sm);color:var(--text-mid);">
                        This cancels the {{ $order->isBooking() ? 'booking' : 'order' }}, returns its utensils to stock and emails
                        the customer that it was cancelled. The flag and reason are only visible to admins.
                    </p>
                    <fieldset class="mb-3">
                        <legend class="cj-label">What happened? <span style="color:var(--required);">*</span></legend>
                        <div class="d-grid gap-2">
                            @foreach(\App\Models\Order::FLAGS as $value => $label)
                                <label class="cj-flag-option">
                                    <input type="radio" name="flag" value="{{ $value }}" required {{ old('flag') === $value ? 'checked' : '' }}>
                                    <span>
                                        <strong>{{ $label }}</strong><br>
                                        <small>{{ $value === 'no_show' ? 'The customer didn\'t show up or couldn\'t be reached on the day.' : 'The booking/order was never genuine (prank, fake details).' }}</small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="mb-3">
                        <label for="flag-reason" class="cj-label">Note (optional)</label>
                        <input type="text" id="flag-reason" name="reason" class="cj-input" maxlength="255"
                               placeholder="e.g. Nobody at the venue, phone off" value="{{ old('reason') }}">
                    </div>
                    @if($order->user->isCustomer() && !$order->user->isBlocked())
                        <label class="cj-flag-option" style="border-style:dashed;">
                            <input type="checkbox" name="block_customer" value="1" {{ old('block_customer') ? 'checked' : '' }}>
                            <span>Also block <strong>{{ $order->user->name }}</strong> from placing new orders</span>
                        </label>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cj"
                            style="background:transparent;color:var(--green-dark);border-color:var(--green-dark);"
                            data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn-cj-danger btn-cj">Cancel &amp; Flag</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@stack('modals')

@if($deliveryPin)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    if (!window.L) return;
    const pin = [{{ $deliveryPin['lat'] }}, {{ $deliveryPin['lng'] }}];
    const map = L.map('admin-pin-map', { scrollWheelZoom: false, dragging: !window.matchMedia('(pointer: coarse)').matches })
        .setView(pin, {{ $deliveryPin['approximate'] ? 15 : 17 }});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    L.marker(pin, { icon: L.divIcon({ className: 'cj-pin-icon', html: '<span></span>', iconSize: [34, 44], iconAnchor: [17, 42] }) }).addTo(map);
})();
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@if($errors->has('reason') && old('_form') === 'reject_receipt')
<script>document.addEventListener('DOMContentLoaded', () => { const m = document.getElementById('rejectReceiptModal'); if (m) new bootstrap.Modal(m).show(); });</script>
@endif
<script>
(function () {
    const modal = document.getElementById('flagModal');
    if (!modal) return;
    // Pre-select whichever button opened the modal (No-show or Fake).
    modal.addEventListener('show.bs.modal', function (e) {
        const flag = e.relatedTarget && e.relatedTarget.dataset.flag;
        if (!flag) return;
        const radio = modal.querySelector(`input[name="flag"][value="${flag}"]`);
        if (radio) radio.checked = true;
    });
    @if(($errors->has('flag') || $errors->has('reason')) && old('_form') !== 'reject_receipt')
        new bootstrap.Modal(modal).show();
    @endif
})();
</script>
@vite(['resources/js/app.js'])
</body>
</html>
