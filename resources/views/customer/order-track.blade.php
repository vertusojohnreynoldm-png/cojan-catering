@extends('layouts.customer')

@section('title', 'Track Order — Cojan Catering')

@section('styles')
@if($tracking && !$tracking['final'])
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endif
@endsection

@section('content')
<div class="d-flex align-items-center gap-2 mb-1">
    <a href="{{ route('customer.dashboard') }}" class="cj-back-link" style="margin-top:0;">← Back to Dashboard</a>
</div>
<h1 class="cj-page-title">Order Tracking</h1>
<p class="cj-page-sub">Order #{{ $order->order_number }}</p>

@if($tracking)
    {{-- MENU ORDERS: stepper + live map (rider → your pin). Bookings below
         keep the original timeline and never get a map. --}}
    <section class="cj-card mb-3 cj-track" aria-label="Live order tracking">
        <div class="cj-card-header">Your Order</div>
        <div class="cj-card-body">
            <x-delivery-stepper :order="$order" />

            @unless($tracking['final'])
                <div class="cj-track-live">
                    <div id="track-map" class="cj-track-map" role="img"
                         aria-label="Map showing your delivery location{{ $tracking['active'] ? ' and your rider' : '' }}"></div>

                    <div class="cj-track-summary" aria-live="polite">
                        <span class="cj-route-figure"><i class="bi bi-signpost-split"></i> <strong id="track-distance">—</strong></span>
                        <span class="cj-route-figure"><i class="bi bi-clock"></i> <strong id="track-eta">—</strong></span>
                        <span class="cj-track-updated" id="track-updated"></span>
                    </div>
                    <div class="cj-track-stale" id="track-stale" role="alert" hidden>
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                        <span><strong>Location stale</strong> — <span id="track-stale-text"></span></span>
                    </div>
                    <p class="cj-track-note" id="track-note">
                        @if(!$tracking['active'])
                            Your rider's live location will appear here once your order is out for delivery.
                        @elseif(!$tracking['rider'])
                            Your rider is on the way — waiting for their first location update…
                        @else
                            Your rider is on the way.
                        @endif
                    </p>
                    @if($tracking['destination']['approximate'])
                        <p class="cj-track-note" style="color:var(--status-amber-text);">
                            <i class="bi bi-info-circle"></i> Your pin is approximate (barangay centre) — the rider may call to confirm the exact spot.
                        </p>
                    @endif
                </div>
            @endunless
        </div>
    </section>
@else
    <!-- Status Timeline (catering bookings) -->
    <div class="cj-card mb-3">
        <div class="cj-card-header">Status</div>
        <div class="cj-card-body">
            <x-order-timeline :order="$order" />
        </div>
    </div>
@endif

<x-delivery-proof-card :order="$order" />

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
                    @unless($order->isBooking())
                    <tr>
                        <td style="padding:6px 0;color:var(--text-light);">Delivery Time</td>
                        <td style="padding:6px 0;">{{ $order->deliveryTimeLabel() }}</td>
                    </tr>
                    @endunless
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
                        <td style="padding:6px 0;">{{ $order->payment_method === 'gcash' ? '📱 GCash' : '💵 Cash on Delivery' }}</td>
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
@if($tracking && !$tracking['final'])
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* Customer live tracking (menu orders). The page NEVER sends coordinates:
   GET track.live returns the rider's last STORED fix, the route from it to
   this order's pin, and whether tracking is still live. Live rider moves
   arrive over the private Echo channel (with heading/speed); the endpoint
   is polled every 30 s as a fallback and re-asked for a route only when
   the rider drifts ~50 m off it or after 60 s. Once the order is delivered
   or cancelled, polling and Echo stop and the page reloads once to show
   the final state (it then renders without any of this). */
(function () {
    const LIVE_URL = {{ \Illuminate\Support\Js::from(route('customer.orders.track.live', $order->order_number)) }};
    const ORDER_ID = {{ $order->id }};
    let state = {{ \Illuminate\Support\Js::from($tracking) }};

    const POLL_MS = 30000, ROUTE_EVERY_MS = 60000, OFF_ROUTE_M = 50, STALE_S = 120, ANIMATE_MS = 900;
    const $ = (id) => document.getElementById(id);
    const distEl = $('track-distance'), etaEl = $('track-eta'), updatedEl = $('track-updated');
    const staleEl = $('track-stale'), staleText = $('track-stale-text'), noteEl = $('track-note');

    const toRad = (d) => d * Math.PI / 180;
    const fmtDistance = (m) => m >= 1000 ? (m / 1000).toFixed(m >= 10000 ? 0 : 1) + ' km' : Math.round(m / 10) * 10 + ' m';
    const fmtEta = (s) => s < 60 ? '< 1 min' : (s < 3600 ? Math.round(s / 60) + ' min' : Math.floor(s / 3600) + ' h ' + Math.round((s % 3600) / 60) + ' min');
    const fmtAgo = (s) => s < 60 ? s + ' s ago' : (s < 3600 ? Math.floor(s / 60) + ' min ago' : 'over an hour ago');

    if (!window.L) { $('track-map').style.display = 'none'; return; }

    const dest = [state.destination.lat, state.destination.lng];
    const map = L.map('track-map', { scrollWheelZoom: false, dragging: !window.matchMedia('(pointer: coarse)').matches })
        .setView(dest, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    L.marker(dest, { icon: L.divIcon({ className: 'cj-pin-icon', html: '<span></span>', iconSize: [34, 44], iconAnchor: [17, 42] }), title: 'Your delivery location' })
        .addTo(map).bindTooltip('You');
    setTimeout(() => map.invalidateSize(), 200);

    // ---- rider marker: glide between fixes, point by heading -------------------
    let rider = null, shown = null, frame = 0, heading = null;
    function applyHeading() {
        const el = rider && rider.getElement();
        if (!el) return;
        el.classList.toggle('has-heading', heading !== null);
        if (heading !== null) el.querySelector('.cj-rider-arrow').style.transform = `rotate(${heading}deg)`;
    }
    function placeRider(pos, hdg) {
        if (hdg !== null && hdg !== undefined && !isNaN(hdg)) heading = parseFloat(hdg);
        if (!rider) {
            rider = L.marker(pos, {
                icon: L.divIcon({ className: 'cj-rider-marker', html: '<span class="cj-rider-dot2"></span><span class="cj-rider-arrow"></span>', iconSize: [36, 36], iconAnchor: [18, 18] }),
                title: 'Your rider', zIndexOffset: 1000, keyboard: false,
            }).addTo(map).bindTooltip('Rider');
            shown = pos;
            applyHeading();
            if (!routeLine) map.fitBounds(L.latLngBounds([pos, dest]).pad(0.25));
            return;
        }
        cancelAnimationFrame(frame);
        const from = shown, t0 = performance.now();
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const step = (now) => {
            const k = reduce ? 1 : Math.min(1, (now - t0) / ANIMATE_MS);
            const e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2;
            shown = [from[0] + (pos[0] - from[0]) * e, from[1] + (pos[1] - from[1]) * e];
            rider.setLatLng(shown);
            if (k < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
        applyHeading();
    }

    // ---- route ------------------------------------------------------------------
    let routeLine = null, routePoints = [], lastRouteAt = 0, fitted = false;
    function distanceToRoute(p) {
        if (routePoints.length < 2) return Infinity;
        const k = 111320, cos = Math.cos(toRad(p[0]));
        const xy = (q) => [(q[1] - p[1]) * k * cos, (q[0] - p[0]) * k];
        let best = Infinity;
        for (let i = 1; i < routePoints.length; i++) {
            const [ax, ay] = xy(routePoints[i - 1]), [bx, by] = xy(routePoints[i]);
            const dx = bx - ax, dy = by - ay, len = dx * dx + dy * dy;
            const t = len ? Math.max(0, Math.min(1, -(ax * dx + ay * dy) / len)) : 0;
            best = Math.min(best, Math.hypot(ax + t * dx, ay + t * dy));
        }
        return best;
    }
    function drawRoute(r) {
        if (!r) return;
        routePoints = r.geometry;
        lastRouteAt = Date.now();
        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline(routePoints, r.fallback
            ? { color: '#C1441E', weight: 4, opacity: .8, dashArray: '8 10' }
            : { color: '#C1441E', weight: 6, opacity: .85 }).addTo(map);
        distEl.textContent = fmtDistance(r.distance_m);
        etaEl.textContent = (r.fallback ? '~' : '') + fmtEta(r.duration_s);
        if (!fitted) { map.fitBounds(L.latLngBounds(routePoints).pad(0.15)); fitted = true; }
    }

    // ---- "Updated X s ago" + stale warning -----------------------------------------
    let ageBase = null, ageAt = 0;
    function setAge(seconds) { ageBase = seconds; ageAt = Date.now(); tick(); }
    function tick() {
        if (ageBase === null) { updatedEl.textContent = ''; staleEl.hidden = true; return; }
        const age = Math.max(0, Math.round(ageBase + (Date.now() - ageAt) / 1000));
        updatedEl.textContent = 'Updated ' + fmtAgo(age);
        const stale = age >= STALE_S;
        staleEl.hidden = !stale;
        updatedEl.classList.toggle('stale', stale);
        if (stale) staleText.textContent = 'the rider’s last update was ' + fmtAgo(age) + '. They may be in an area with weak signal; the map will catch up automatically.';
    }
    const ticker = setInterval(tick, 1000);

    // ---- apply a state from the server -----------------------------------------------
    let stopped = false, pollTimer = null, channel = null;
    function finish() {
        if (stopped) return;
        stopped = true;
        clearInterval(pollTimer);
        clearInterval(ticker);
        try { if (channel && window.Echo) window.Echo.leave(`delivery.${ORDER_ID}`); } catch (e) {}
        // Reload once: the server now renders the final state (no live map).
        window.location.reload();
    }
    function apply(s) {
        state = s;
        if (s.final) { finish(); return; }
        if (s.rider) {
            placeRider([s.rider.lat, s.rider.lng], s.rider.heading);
            setAge(s.rider.age_seconds ?? 0);
            noteEl.textContent = 'Your rider is on the way.';
        } else if (s.active) {
            noteEl.textContent = 'Your rider is on the way — waiting for their first location update…';
        }
        if (s.route) drawRoute(s.route);
    }

    async function refresh() {
        if (stopped) return;
        try {
            const resp = await fetch(LIVE_URL, { headers: { 'Accept': 'application/json' } });
            if (resp.ok) apply(await resp.json()); // 429 keeps what is already shown
        } catch (e) { /* offline: the next poll retries */ }
    }

    apply(state);
    // The page already arrives with the first route; only ask right away
    // if it did not (e.g. the rider had no location yet when it rendered).
    if (state.active && !state.route) refresh();
    pollTimer = setInterval(refresh, POLL_MS);

    // Live moves (heading/speed included) between polls.
    if (window.Echo && state.active) {
        channel = window.Echo.private(`delivery.${ORDER_ID}`).listen('DeliveryLocationUpdated', (e) => {
            if (stopped) return;
            const pos = [parseFloat(e.lat), parseFloat(e.lng)];
            placeRider(pos, e.heading);
            setAge(0);
            noteEl.textContent = 'Your rider is on the way.';
            if (!routeLine || Date.now() - lastRouteAt > ROUTE_EVERY_MS || distanceToRoute(pos) > OFF_ROUTE_M) refresh();
        });
    }
})();
</script>
@endif
@endsection
