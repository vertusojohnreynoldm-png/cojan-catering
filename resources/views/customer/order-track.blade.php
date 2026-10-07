@extends('layouts.customer')

@section('title', 'Track Order — Cojan Catering')

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endsection

@section('content')
<div class="d-flex align-items-center gap-2 mb-1">
    <a href="{{ route('customer.dashboard') }}"
       style="color:var(--green-dark);text-decoration:none;font-size:.9rem;">← Back to Dashboard</a>
</div>
<h1 class="cj-page-title">Order Tracking</h1>
<p class="cj-page-sub">Order #{{ $order->order_number }}</p>

@php
    $trackingActive = $order->delivery
        && in_array($order->delivery->status, ['picked_up', 'in_transit'])
        && $order->delivery->current_lat !== null
        && $order->delivery->current_lng !== null;
@endphp

<div class="cj-card mb-3">
    <div class="cj-card-header">Live Rider Location</div>
    <div class="cj-card-body">
        <div id="delivery-map" style="height:320px;border-radius:10px;{{ $trackingActive ? '' : 'display:none;' }}"></div>
        <p id="delivery-map-updated" style="font-size:.78rem;color:var(--text-light);margin-top:.5rem;margin-bottom:0;{{ $trackingActive ? '' : 'display:none;' }}"></p>
        <p id="delivery-map-waiting" style="color:var(--text-light);margin:0;{{ $trackingActive ? 'display:none;' : '' }}">
            Live tracking will appear once your order is picked up.
        </p>
    </div>
</div>

<!-- Status Timeline -->
<div class="cj-card mb-3">
    <div class="cj-card-header">Delivery Status</div>
    <div class="cj-card-body">
        <x-order-timeline :order="$order" />
    </div>
</div>

<div class="row g-3">
    <!-- Order Info -->
    <div class="col-12 col-md-6">
        <div class="cj-card h-100">
            <div class="cj-card-header">Order Information</div>
            <div class="cj-card-body">
                <table style="width:100%;font-size:.9rem;border-collapse:collapse;">
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);width:40%;">Order #</td>
                        <td style="padding:6px 0;font-weight:600;">{{ $order->order_number }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Address</td>
                        <td style="padding:6px 0;">{{ $order->delivery_address }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Contact</td>
                        <td style="padding:6px 0;">{{ $order->delivery_phone }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Total</td>
                        <td style="padding:6px 0;font-weight:600;color:var(--green-dark);">
                            ₱{{ number_format($order->total_amount, 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Payment</td>
                        <td style="padding:6px 0;">💵 Cash on Delivery</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Items -->
    <div class="col-12 col-md-6">
        <div class="cj-card h-100">
            <div class="cj-card-header">Items Ordered</div>
            <div class="cj-card-body p-0">
                <div class="table-responsive">
                    <table class="cj-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderItems as $item)
                            <tr>
                                <td>{{ $item->displayName() }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>₱{{ number_format($item->subtotal, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const orderId = {{ $order->id }};
    const mapEl = document.getElementById('delivery-map');
    const updatedEl = document.getElementById('delivery-map-updated');
    const waitingEl = document.getElementById('delivery-map-waiting');
    const SAN_JOSE = [12.3585, 121.0687]; // San Jose, Occidental Mindoro
    const STALE_AFTER_MS = 60000;
    let map = null;
    let marker = null;
    let accuracyCircle = null;
    let lastUpdateTimestamp = null;

    function ensureMap(lat, lng) {
        if (map) return;
        mapEl.style.display = 'block';
        updatedEl.style.display = 'block';
        waitingEl.style.display = 'none';
        const center = (lat && lng) ? [lat, lng] : SAN_JOSE;
        map = L.map('delivery-map').setView(center, 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19,
        }).addTo(map);
        marker = L.marker(center).addTo(map).bindPopup('Rider location');
    }

    function refreshStaleness() {
        if (lastUpdateTimestamp === null) return;
        const ageMs = Date.now() - lastUpdateTimestamp;
        if (ageMs > STALE_AFTER_MS) {
            updatedEl.textContent = 'Rider location may be outdated';
            updatedEl.style.color = 'var(--danger)';
        } else {
            updatedEl.textContent = 'Last updated ' + new Date(lastUpdateTimestamp).toLocaleTimeString();
            updatedEl.style.color = '';
        }
    }
    setInterval(refreshStaleness, 15000);

    function updateMarker(lat, lng, updatedAt, accuracy) {
        ensureMap(lat, lng);
        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng]);

        if (accuracy !== null && accuracy !== undefined) {
            if (accuracyCircle) {
                accuracyCircle.setLatLng([lat, lng]);
                accuracyCircle.setRadius(accuracy);
            } else {
                accuracyCircle = L.circle([lat, lng], {
                    radius: accuracy,
                    color: '#C1441E',
                    fillColor: '#C1441E',
                    fillOpacity: 0.15,
                    weight: 1,
                }).addTo(map);
            }
        }

        lastUpdateTimestamp = updatedAt ? new Date(updatedAt).getTime() : Date.now();
        refreshStaleness();
    }

    @if($trackingActive)
        updateMarker(
            {{ $order->delivery->current_lat }},
            {{ $order->delivery->current_lng }},
            '{{ $order->delivery->last_location_update?->toIso8601String() }}',
            {{ $order->delivery->current_accuracy ?? 'null' }}
        );
    @endif

    @if($order->delivery && in_array($order->delivery->status, ['picked_up', 'in_transit']))
        if (window.Echo) {
            window.Echo.private(`delivery.${orderId}`).listen('DeliveryLocationUpdated', (e) => {
                updateMarker(e.lat, e.lng, e.updated_at, e.accuracy);
            });
        }
    @endif
})();
</script>
@endsection