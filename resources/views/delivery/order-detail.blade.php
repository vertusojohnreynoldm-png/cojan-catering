<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Delivery Detail - Cojan Catering</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/cojan.css') }}">
    <style>
        .cj-nav { background: #92400e; }
        .cj-nav-brand span { color: #E8A93B; }
        .cj-card-header { background: #92400e; }
        .cj-table thead tr { background: #92400e; }
        .cj-table tbody tr:hover { background: #fef3c7; }
        .btn-del { background: #d97706; color: white; border: none; padding: .45rem 1.1rem; border-radius: 6px; font-size: .85rem; font-weight: 500; cursor: pointer; transition: all .22s ease; text-decoration: none; display: inline-block; }
        .btn-del:hover { background: #b45309; color: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(217,119,6,.3); }
    </style>
</head>
<body>
<nav class="cj-nav">
    <a href="{{ route('delivery.dashboard') }}" class="cj-nav-brand">🚚 Cojan <span>Delivery</span></a>
    <div class="cj-nav-links">
        <button type="button" class="btn-cj-amber btn-cj btn-cj-sm" data-bs-toggle="modal" data-bs-target="#logoutModal">Logout</button>
    </div>
</nav>
<x-logout-modal />
<x-toast />

<div class="cj-page">
    <h1 class="cj-page-title">Delivery Detail</h1>
    <p class="cj-page-sub">Order #{{ $delivery->order->order_number }}</p>

    <div class="row g-3">
        <!-- Customer Info -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Customer Information</div>
                <div class="cj-card-body">
                    <p><strong>Name:</strong> {{ $delivery->order->user->name }}</p>
                    <p><strong>Phone:</strong> {{ $delivery->order->delivery_phone }}</p>
                    <p><strong>Address:</strong> {{ $delivery->order->delivery_address }}</p>
                    @if($delivery->order->notes)
                        <p><strong>Notes:</strong> {{ $delivery->order->notes }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Update Status -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Update Delivery Status</div>
                <div class="cj-card-body">
                    <p><strong>Current Status:</strong>
                        <span class="badge-cj {{ $delivery->status === 'delivered' ? 'badge-delivered' : ($delivery->status === 'failed' ? 'badge-cancelled' : 'badge-pending') }}">
                            {{ ucfirst(str_replace('_', ' ', $delivery->status)) }}
                        </span>
                    </p>
                    @if($delivery->assigned_at)
                        <p><strong>Assigned:</strong> {{ $delivery->assigned_at->format('M d, Y h:i A') }}</p>
                    @endif
                    @if($delivery->picked_up_at)
                        <p><strong>Picked Up:</strong> {{ $delivery->picked_up_at->format('M d, Y h:i A') }}</p>
                    @endif
                    @if($delivery->delivered_at)
                        <p><strong>Delivered:</strong> {{ $delivery->delivered_at->format('M d, Y h:i A') }}</p>
                    @endif

                    @if($delivery->status !== 'delivered' && $delivery->status !== 'failed')
                        <form method="POST" action="{{ route('delivery.orders.status', $delivery->id) }}">
                            @csrf
                            <div class="d-flex gap-2">
                                <select name="status" class="cj-select">
                                    <option value="assigned" {{ $delivery->status == 'assigned' ? 'selected' : '' }}>Assigned</option>
                                    <option value="picked_up" {{ $delivery->status == 'picked_up' ? 'selected' : '' }}>Picked Up</option>
                                    <option value="in_transit" {{ $delivery->status == 'in_transit' ? 'selected' : '' }}>In Transit</option>
                                    <option value="delivered" {{ $delivery->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="failed" {{ $delivery->status == 'failed' ? 'selected' : '' }}>Failed</option>
                                </select>
                                <button type="submit" class="btn-del">Update</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="cj-card mt-3">
        <div class="cj-card-header">Items to Deliver</div>
        <div class="cj-card-body">
            <div class="table-responsive">
                <table class="cj-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($delivery->order->orderItems as $item)
                            <tr>
                                <td>{{ $item->displayName() }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" class="text-end"><strong>Total Amount:</strong></td>
                            <td><strong>₱{{ number_format($delivery->order->total_amount, 2) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="alert-cj alert-warning mt-3">
                💵 <strong>Payment:</strong> Cash on Delivery — Collect ₱{{ number_format($delivery->order->total_amount, 2) }} upon delivery.
            </div>
        </div>
    </div>

    <a href="{{ route('delivery.dashboard') }}"
       style="display:inline-block;margin-top:1rem;color:#92400e;border:1.5px solid #92400e;
              border-radius:10px;padding:8px 18px;text-decoration:none;font-size:.9rem;">
        ← Back to Dashboard
    </a>
</div>

@if(in_array($delivery->status, ['picked_up', 'in_transit']))
<div id="location-status" class="alert-cj mt-2" style="display:none;max-width:600px;"></div>
<p id="gps-accuracy-status" class="mt-2" style="display:none;max-width:600px;font-size:.85rem;font-weight:600;"></p>
<script>
(function () {
    const deliveryId = {{ $delivery->id }};
    const statusEl = document.getElementById('location-status');
    const accuracyEl = document.getElementById('gps-accuracy-status');
    const THROTTLE_MS = 7000;
    const GOOD_ACCURACY_M = 100;   // readings at/under this are considered a real GPS lock
    const HARD_REJECT_M = 5000;    // readings worse than this are almost certainly an IP-based guess — always ignored
    const GRACE_PERIOD_MS = 30000; // avoids sending the rough Wi-Fi/IP guess before GPS locks on
    const PAGE_LOAD_TIME = Date.now();

    let lastSent = 0;
    let watchId = null;
    let locked = false;     // true once we've either seen a good fix or timed out the grace period
    let bestSoFar = null;   // { lat, lng, accuracy } — best reading seen during the grace period

    function showStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.className = 'alert-cj mt-2 ' + (isError ? 'alert-danger' : 'alert-info');
        statusEl.style.display = 'block';
    }

    function updateAccuracyStatus(accuracy) {
        if (!accuracyEl) return;
        const rounded = Math.round(accuracy);
        if (accuracy <= GOOD_ACCURACY_M) {
            accuracyEl.textContent = `GPS accuracy: ±${rounded} m`;
            accuracyEl.style.color = '#15803d';
        } else {
            accuracyEl.textContent = `Waiting for a better GPS signal (±${rounded} m)... try going outside or enabling precise location.`;
            accuracyEl.style.color = '#92400e';
        }
        accuracyEl.style.display = 'block';
    }

    function sendLocation(lat, lng, accuracy) {
        fetch(`{{ url('/delivery/orders') }}/${deliveryId}/location`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ lat, lng, accuracy }),
        }).catch(() => { /* transient network failure — the next watchPosition tick retries */ });
    }

    function maybeSend(lat, lng, accuracy) {
        const now = Date.now();
        if (now - lastSent < THROTTLE_MS) return;
        lastSent = now;
        sendLocation(lat, lng, accuracy);
    }

    // Independent safety net: if readings arrive sparsely enough that no
    // watchPosition callback happens to land right at the 30s mark, this
    // still forces the transition on schedule rather than leaving the rider
    // waiting indefinitely for a fix that may never fully lock on.
    setTimeout(() => {
        if (!locked && bestSoFar) {
            locked = true;
            maybeSend(bestSoFar.lat, bestSoFar.lng, bestSoFar.accuracy);
        }
    }, GRACE_PERIOD_MS);

    if (!navigator.geolocation) {
        showStatus('Live location sharing is not supported on this device/browser — the customer won\'t see live tracking for this delivery.', true);
    } else {
        watchId = navigator.geolocation.watchPosition(
            (position) => {
                statusEl.style.display = 'none';

                const accuracy = position.coords.accuracy;
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                updateAccuracyStatus(accuracy);

                if (accuracy > HARD_REJECT_M) {
                    return; // near-certain IP-based guess — ignored outright, grace period or not
                }

                if (locked) {
                    maybeSend(lat, lng, accuracy);
                    return;
                }

                if (accuracy <= GOOD_ACCURACY_M) {
                    locked = true;
                    maybeSend(lat, lng, accuracy);
                    return;
                }

                // Still within the grace period with no good fix yet — track the
                // best reading in case the window times out before one arrives.
                if (!bestSoFar || accuracy < bestSoFar.accuracy) {
                    bestSoFar = { lat, lng, accuracy };
                }

                if (Date.now() - PAGE_LOAD_TIME >= GRACE_PERIOD_MS) {
                    locked = true;
                    maybeSend(bestSoFar.lat, bestSoFar.lng, bestSoFar.accuracy);
                }
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    showStatus('Location sharing is off — please allow location access in your browser so the customer can see live tracking.', true);
                } else {
                    showStatus('Having trouble getting your location right now — live tracking may be delayed, but the rest of the delivery still works normally.', true);
                }
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 }
        );
    }

    window.addEventListener('beforeunload', () => {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    });

    // Keep the screen awake so the phone doesn't lock and pause location
    // updates. Best-effort only — silently skipped if unsupported or denied.
    let wakeLock = null;

    async function requestWakeLock() {
        if (!('wakeLock' in navigator)) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
        } catch (e) {
            // Unsupported, denied, or the tab isn't visible — not critical, skip silently.
        }
    }

    requestWakeLock();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            requestWakeLock();
        }
    });
})();
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
