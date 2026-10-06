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
<script>
(function () {
    const deliveryId = {{ $delivery->id }};
    const statusEl = document.getElementById('location-status');
    const THROTTLE_MS = 7000;
    let lastSent = 0;
    let watchId = null;

    function showStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.className = 'alert-cj mt-2 ' + (isError ? 'alert-danger' : 'alert-info');
        statusEl.style.display = 'block';
    }

    function sendLocation(lat, lng) {
        fetch(`{{ url('/delivery/orders') }}/${deliveryId}/location`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ lat, lng }),
        }).catch(() => { /* transient network failure — the next watchPosition tick retries */ });
    }

    if (!navigator.geolocation) {
        showStatus('Live location sharing is not supported on this device/browser — the customer won\'t see live tracking for this delivery.', true);
    } else {
        watchId = navigator.geolocation.watchPosition(
            (position) => {
                statusEl.style.display = 'none';
                const now = Date.now();
                if (now - lastSent < THROTTLE_MS) return;
                lastSent = now;
                sendLocation(position.coords.latitude, position.coords.longitude);
            },
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    showStatus('Location sharing is off — please allow location access in your browser so the customer can see live tracking.', true);
                } else {
                    showStatus('Having trouble getting your location right now — live tracking may be delayed, but the rest of the delivery still works normally.', true);
                }
            },
            { enableHighAccuracy: true, maximumAge: 5000 }
        );
    }

    window.addEventListener('beforeunload', () => {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
    });
})();
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
