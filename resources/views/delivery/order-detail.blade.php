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
    @if($nav)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endif
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

    @if($nav)
        {{-- Rider navigation — menu orders only, active deliveries only
             ($nav is null otherwise, so the customer's location never
             reaches this page). Phone-first: big map, big Navigate. --}}
        <section class="cj-card cj-rider-nav mb-3" aria-label="Navigation to the customer">
            <div id="rider-map" class="cj-rider-map" role="application" aria-label="Map with your position, the customer's location and the route"></div>
            <div class="cj-card-body">
                <div class="cj-route-summary" id="route-summary" aria-live="polite">
                    <span class="cj-route-figure"><i class="bi bi-signpost-split"></i> <strong id="route-distance">—</strong></span>
                    <span class="cj-route-figure"><i class="bi bi-clock"></i> <strong id="route-eta">—</strong></span>
                    <span class="cj-route-note" id="route-note">Finding your location…</span>
                </div>

                {{-- Live-sharing status: one line the rider can glance at,
                     plus any permission / GPS problem with how to fix it. --}}
                <div class="cj-share-status" id="share-status" aria-live="polite">
                    <span class="cj-share-pill" id="share-pill">Starting GPS…</span>
                    <span class="cj-share-meta" id="share-meta"></span>
                </div>
                <div class="cj-share-help" id="share-help" role="alert" hidden></div>

                <a href="{{ $nav['google'] }}" target="_blank" rel="noopener" class="cj-navigate-btn">
                    <i class="bi bi-cursor-fill" aria-hidden="true"></i> Navigate
                    <small>Google Maps</small>
                </a>
                <div class="cj-nav-secondary">
                    <a href="{{ $nav['waze'] }}" target="_blank" rel="noopener" class="btn-cj cj-nav-alt">
                        <i class="bi bi-sign-turn-right" aria-hidden="true"></i> Waze
                    </a>
                    <a href="{{ $nav['tel'] }}" class="btn-cj cj-nav-alt cj-nav-call">
                        <i class="bi bi-telephone-fill" aria-hidden="true"></i> Call {{ \Illuminate\Support\Str::of($delivery->order->contact_name ?: $delivery->order->user->name)->before(' ') }}
                    </a>
                </div>

                <div class="cj-nav-dest">
                    <p class="mb-1"><strong>{{ $delivery->order->contact_name ?: $delivery->order->user->name }}</strong> · <a href="{{ $nav['tel'] }}">{{ $delivery->order->delivery_phone }}</a></p>
                    <p class="mb-1">{{ $delivery->order->street }}@if($delivery->order->barangay), {{ $delivery->order->barangay }}@endif</p>
                    @if($delivery->order->landmark)
                        <p class="mb-1"><i class="bi bi-geo"></i> Landmark: {{ $delivery->order->landmark }}</p>
                    @endif
                    @if($nav['approximate'])
                        <p class="cj-nav-approx mb-0" role="note">
                            <i class="bi bi-exclamation-triangle"></i>
                            Location approximate — this order has no map pin, so this is the barangay centre. Call the customer to confirm the exact spot.
                        </p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <div class="row g-3">
        <!-- Customer Info -->
        <div class="col-12 col-md-6">
            <div class="cj-card h-100">
                <div class="cj-card-header">Customer Information</div>
                <div class="cj-card-body">
                    <p><strong>Name:</strong> {{ $delivery->order->user->name }}</p>
                    <p><strong>Phone:</strong> {{ $delivery->order->delivery_phone }}</p>
                    @unless($delivery->order->isBooking())
                        <p><strong>Delivery Time:</strong> {{ $delivery->order->deliveryTimeLabel() }}</p>
                    @endunless
                    <p>
                        <strong>Address:</strong>
                        @if($delivery->order->barangay)
                            <span class="badge-cj badge-booking-type">{{ $delivery->order->barangay }}</span>
                        @endif
                        {{ $delivery->order->delivery_address }}
                    </p>
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
                                    <option value="failed" {{ $delivery->status == 'failed' ? 'selected' : '' }}>Failed</option>
                                </select>
                                <button type="submit" class="btn-cj-amber">Update</button>
                            </div>
                        </form>
                        <p style="font-size:.78rem;color:var(--text-light);margin-top:.5rem;margin-bottom:0;">
                            To mark this delivered, use "Mark as Delivered" below — it requires a photo.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($delivery->status === 'delivered')
        <!-- Delivery Proof — the rider's own record of what they submitted -->
        <div class="cj-card mt-3">
            <div class="cj-card-header">Delivery Proof</div>
            <div class="cj-card-body">
                @if($delivery->proof_photo_path)
                    <img src="{{ route('orders.proof-photo', $delivery->order_id) }}" alt="Proof of delivery photo"
                         style="width:100%;max-width:320px;border-radius:var(--radius-base);display:block;margin-bottom:.75rem;box-shadow:var(--shadow-sm);">
                @endif
                @if($delivery->received_by)
                    <p><strong>Received by:</strong> {{ $delivery->received_by }}</p>
                @endif
                @if($delivery->delivery_note)
                    <p><strong>Note:</strong> {{ $delivery->delivery_note }}</p>
                @endif
                @if(!$delivery->proof_photo_path && !$delivery->received_by && !$delivery->delivery_note)
                    {{-- Deliveries completed before proof photos existed. --}}
                    <p class="mb-0" style="color:var(--text-light);">No proof photo or note was recorded for this delivery.</p>
                @endif
            </div>
        </div>
    @elseif($delivery->status !== 'failed')
        <!-- Mark as Delivered — phone-first: big camera button, preview,
             clear retry-safe loading state. Submits via fetch() (see script
             below), never a plain form POST, specifically so a failed
             upload on a poor connection can be retried without losing the
             already-compressed photo or the typed fields. -->
        <div class="cj-card mt-3">
            <div class="cj-card-header">Mark as Delivered</div>
            <div class="cj-card-body">
                <div id="pod-camera-area">
                    <label for="pod-photo-input" id="pod-camera-btn"
                           style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                                  gap:.5rem;width:100%;min-height:120px;border:2px dashed var(--green-dark);
                                  border-radius:var(--radius-md);cursor:pointer;background:var(--cream);color:var(--green-dark);">
                        <i class="bi bi-camera-fill" style="font-size:2rem;" aria-hidden="true"></i>
                        <span style="font-weight:600;">Take Delivery Photo</span>
                    </label>
                    <input type="file" id="pod-photo-input" accept="image/*" capture="environment" class="visually-hidden">
                </div>

                <div id="pod-preview-area" style="display:none;margin-top:.75rem;text-align:center;">
                    <img id="pod-preview-img" alt="Delivery photo preview" style="max-width:100%;max-height:320px;border-radius:var(--radius-base);box-shadow:var(--shadow-sm);">
                    <div style="margin-top:.5rem;">
                        <button type="button" id="pod-retake-btn" class="btn-cj-outline btn-cj btn-cj-sm">Retake Photo</button>
                    </div>
                </div>

                <div class="mb-3 mt-3">
                    <label class="cj-label">Received By (Optional)</label>
                    <input type="text" id="pod-received-by" class="cj-input" placeholder="Name of person who received it">
                </div>
                <div class="mb-2">
                    <label class="cj-label">Note (Optional)</label>
                    <textarea id="pod-note" class="cj-textarea" rows="2" placeholder="Any delivery notes..."></textarea>
                </div>
                <p style="font-size:.78rem;color:var(--text-light);margin-bottom:.75rem;display:flex;align-items:center;gap:.35rem;">
                    <i class="bi bi-shield-lock" aria-hidden="true"></i> Photo is visible only to the customer and admin.
                </p>

                <div id="pod-error" class="alert-cj alert-danger" style="display:none;"></div>

                <button type="button" id="pod-submit-btn" class="btn-cj btn-cj" style="width:100%;padding:.75rem;" disabled>
                    Confirm Delivery
                </button>
            </div>
        </div>

        <script>
        (function () {
            const fileInput = document.getElementById('pod-photo-input');
            const previewArea = document.getElementById('pod-preview-area');
            const previewImg = document.getElementById('pod-preview-img');
            const retakeBtn = document.getElementById('pod-retake-btn');
            const submitBtn = document.getElementById('pod-submit-btn');
            const errorEl = document.getElementById('pod-error');
            const receivedByInput = document.getElementById('pod-received-by');
            const noteInput = document.getElementById('pod-note');

            let compressedBlob = null;
            let submitting = false;

            function showError(msg) {
                errorEl.textContent = msg;
                errorEl.style.display = 'block';
            }
            function hideError() {
                errorEl.style.display = 'none';
            }

            // Resize to at most 1280px on the long edge and re-encode as
            // JPEG ~0.7 quality — keeps a rider's mobile-data upload small
            // without the server needing to do any image processing itself.
            function compressImage(file) {
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    const reader = new FileReader();
                    reader.onload = (e) => { img.src = e.target.result; };
                    reader.onerror = () => reject(new Error('read failed'));
                    img.onload = () => {
                        const MAX = 1280;
                        let { width, height } = img;
                        if (width > MAX || height > MAX) {
                            if (width > height) { height = Math.round(height * MAX / width); width = MAX; }
                            else { width = Math.round(width * MAX / height); height = MAX; }
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = width;
                        canvas.height = height;
                        canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                        canvas.toBlob((blob) => {
                            if (blob) resolve(blob); else reject(new Error('compression failed'));
                        }, 'image/jpeg', 0.7);
                    };
                    img.onerror = () => reject(new Error('decode failed'));
                    reader.readAsDataURL(file);
                });
            }

            fileInput.addEventListener('change', async () => {
                const file = fileInput.files[0];
                if (!file) return;
                hideError();
                try {
                    compressedBlob = await compressImage(file);
                    previewImg.src = URL.createObjectURL(compressedBlob);
                    previewArea.style.display = 'block';
                    submitBtn.disabled = false;
                } catch (e) {
                    showError('Could not process that photo — please try again.');
                    compressedBlob = null;
                    submitBtn.disabled = true;
                }
            });

            retakeBtn.addEventListener('click', () => {
                compressedBlob = null;
                previewArea.style.display = 'none';
                submitBtn.disabled = true;
                fileInput.value = '';
                hideError();
            });

            function getLocationOnce() {
                return new Promise((resolve) => {
                    if (!navigator.geolocation) return resolve(null);
                    navigator.geolocation.getCurrentPosition(
                        (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }),
                        () => resolve(null),
                        { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
                    );
                });
            }

            async function submitDelivery() {
                if (submitting) return; // double-tap protection
                if (!compressedBlob) {
                    showError('Please take a delivery photo first.');
                    return;
                }
                submitting = true;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Uploading...';
                hideError();

                const loc = await getLocationOnce();

                const formData = new FormData();
                formData.append('status', 'delivered');
                formData.append('photo', compressedBlob, 'delivery-proof.jpg');
                if (receivedByInput.value) formData.append('received_by', receivedByInput.value);
                if (noteInput.value) formData.append('delivery_note', noteInput.value);
                if (loc) {
                    formData.append('proof_lat', loc.lat);
                    formData.append('proof_lng', loc.lng);
                    formData.append('proof_accuracy', loc.accuracy);
                }

                try {
                    const resp = await fetch('{{ route('delivery.orders.status', $delivery->id) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });

                    if (resp.ok) {
                        const data = await resp.json();
                        window.location.href = data.redirect || window.location.href;
                        return;
                    }

                    if (resp.status === 422) {
                        const data = await resp.json();
                        const firstError = data.errors ? Object.values(data.errors)[0][0] : 'Please check the form and try again.';
                        showError(firstError);
                    } else {
                        showError('Upload failed — check your connection and tap Confirm Delivery to retry.');
                    }
                } catch (e) {
                    showError('Upload failed — check your connection and tap Confirm Delivery to retry.');
                }

                // Deliberately does NOT clear compressedBlob/preview/fields —
                // the whole point is that a retry just re-sends the same
                // already-compressed photo without the rider redoing anything.
                submitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Confirm Delivery';
            }

            submitBtn.addEventListener('click', submitDelivery);
        })();
        </script>
    @endif

    <!-- Order Items -->
    <div class="cj-card mt-3">
        <div class="cj-card-header">Items to Deliver</div>
        <div class="cj-card-body">
            <div class="table-responsive">
                <table class="cj-table cj-table-stack">
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
                                <td class="cj-td-title">{{ $item->displayName() }}</td>
                                <td data-label="Quantity">{{ $item->quantity }}</td>
                                <td data-label="Price">₱{{ number_format($item->subtotal, 2) }}</td>
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
            @if($delivery->order->payment_method === 'gcash')
                @if($delivery->order->payment_status === 'paid')
                    <div class="alert-cj alert-success mt-3">
                        📱 <strong>Payment:</strong> Paid via GCash — no cash to collect.
                    </div>
                @else
                    <div class="alert-cj alert-danger mt-3">
                        📱 <strong>Payment:</strong> GCash (Ref: {{ $delivery->order->gcash_reference ?: 'none on file' }}) —
                        not yet confirmed as paid. Do not accept cash for this order; contact the admin if unsure.
                    </div>
                @endif
            @else
                <div class="alert-cj alert-warning mt-3">
                    💵 <strong>Payment:</strong> Cash on Delivery — Collect ₱{{ number_format($delivery->order->total_amount, 2) }} upon delivery.
                </div>
            @endif
        </div>
    </div>

    <a href="{{ route('delivery.dashboard') }}" class="cj-back-link">
        ← Back to Dashboard
    </a>
</div>

@if($nav)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* Rider GPS — ONE watcher feeding three things (menu orders only; this
   block only renders for an active menu delivery):
     1. the map: smooth-moving rider marker pointed by heading;
     2. the route: re-requested after ~50 m off-route or every 60 s
        (GET delivery.orders.route — the server uses the order's pin);
     3. live sharing to the customer: POST delivery.orders.location every
        10 s or after 20 m of movement (never closer than 3 s apart), only
        while Picked Up / In Transit. If the server answers with stop:true
        (delivered, failed or cancelled) the watch is cleared, the screen
        wake lock released and sharing ends for good.
   Readings worse than 100 m are ignored after the first fix; before it, a
   30 s grace period accepts the best rough fix so the map isn't empty. */
(function () {
    {{-- Js::from, not @json: @json splits its argument on commas. --}}
    const ROUTE_URL = {{ \Illuminate\Support\Js::from(route('delivery.orders.route', $delivery->id)) }};
    const LOCATION_URL = {{ \Illuminate\Support\Js::from(route('delivery.orders.location', $delivery->id)) }};
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const SHARING = {{ in_array($delivery->status, ['picked_up', 'in_transit'], true) ? 'true' : 'false' }};
    const dest = [{{ $nav['lat'] }}, {{ $nav['lng'] }}];

    const SEND_EVERY_MS = 10000, MIN_GAP_MS = 3000, SEND_AFTER_M = 20;
    const GOOD_ACCURACY_M = 100, HARD_REJECT_M = 5000, GRACE_MS = 30000;
    const OFF_ROUTE_M = 50, ROUTE_EVERY_MS = 60000, ANIMATE_MS = 900;
    const startedAt = Date.now();

    const $ = (id) => document.getElementById(id);
    const distEl = $('route-distance'), etaEl = $('route-eta'), noteEl = $('route-note');
    const pill = $('share-pill'), meta = $('share-meta'), help = $('share-help');

    // ---- small helpers ----------------------------------------------------
    const toRad = (d) => d * Math.PI / 180;
    function metres(a, b) {
        const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) ** 2;
        return 2 * 6371000 * Math.asin(Math.min(1, Math.sqrt(h)));
    }
    function bearing(a, b) {
        const y = Math.sin(toRad(b[1] - a[1])) * Math.cos(toRad(b[0]));
        const x = Math.cos(toRad(a[0])) * Math.sin(toRad(b[0])) - Math.sin(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.cos(toRad(b[1] - a[1]));
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }
    const fmtDistance = (m) => m >= 1000 ? (m / 1000).toFixed(m >= 10000 ? 0 : 1) + ' km' : Math.round(m / 10) * 10 + ' m';
    const fmtEta = (s) => s < 60 ? '< 1 min' : (s < 3600 ? Math.round(s / 60) + ' min' : Math.floor(s / 3600) + ' h ' + Math.round((s % 3600) / 60) + ' min');

    function setPill(text, tone) { pill.textContent = text; pill.className = 'cj-share-pill ' + (tone || ''); }
    function setHelp(html) { help.hidden = !html; help.innerHTML = html || ''; }

    // ---- map ----------------------------------------------------------------
    let map = null;
    if (window.L) {
        map = L.map('rider-map', { scrollWheelZoom: false }).setView(dest, 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
        L.marker(dest, {
            icon: L.divIcon({ className: 'cj-pin-icon', html: '<span></span>', iconSize: [34, 44], iconAnchor: [17, 42] }),
            title: 'Customer location',
        }).addTo(map);
        setTimeout(() => map.invalidateSize(), 200);
    } else {
        $('rider-map').style.display = 'none';
        noteEl.textContent = 'The map could not load — use the Navigate button.';
    }

    let riderMarker = null, accuracyCircle = null, shown = null, animFrame = 0, heading = null, lastPos = null;

    function riderIcon() {
        return L.divIcon({ className: 'cj-rider-marker', html: '<span class="cj-rider-dot2"></span><span class="cj-rider-arrow"></span>', iconSize: [36, 36], iconAnchor: [18, 18] });
    }
    function applyHeading() {
        const el = riderMarker && riderMarker.getElement();
        if (!el) return;
        el.classList.toggle('has-heading', heading !== null);
        if (heading !== null) el.querySelector('.cj-rider-arrow').style.transform = `rotate(${heading}deg)`;
    }
    // Glide from where the marker is to the new fix instead of jumping.
    function moveRider(to, accuracy) {
        if (!map) return;
        if (!riderMarker) {
            riderMarker = L.marker(to, { icon: riderIcon(), title: 'You', zIndexOffset: 1000, keyboard: false }).addTo(map);
            accuracyCircle = L.circle(to, { radius: accuracy, color: '#2563eb', weight: 1, fillOpacity: .08 }).addTo(map);
            shown = to;
            applyHeading();
            // Frame rider + customer right away; the route refits when it arrives.
            if (!fitted) map.fitBounds(L.latLngBounds([to, dest]).pad(0.25));
            return;
        }
        accuracyCircle.setRadius(accuracy);
        cancelAnimationFrame(animFrame);
        const from = shown, t0 = performance.now();
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const step = (now) => {
            const k = reduce ? 1 : Math.min(1, (now - t0) / ANIMATE_MS);
            const e = k < .5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2; // ease in-out
            shown = [from[0] + (to[0] - from[0]) * e, from[1] + (to[1] - from[1]) * e];
            riderMarker.setLatLng(shown);
            accuracyCircle.setLatLng(shown);
            if (k < 1) animFrame = requestAnimationFrame(step);
        };
        animFrame = requestAnimationFrame(step);
        applyHeading();
    }

    // ---- route ----------------------------------------------------------------
    let routeLine = null, routePoints = [], lastRouteAt = 0, routeInFlight = false, routeBlockedUntil = 0, fitted = false;

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
    function drawRoute(data) {
        routePoints = data.geometry;
        if (!map) return;
        if (routeLine) map.removeLayer(routeLine);
        routeLine = L.polyline(routePoints, data.fallback
            ? { color: '#C1441E', weight: 4, opacity: .8, dashArray: '8 10' }
            : { color: '#C1441E', weight: 6, opacity: .85 }).addTo(map);
        distEl.textContent = fmtDistance(data.distance_m);
        etaEl.textContent = (data.fallback ? '~' : '') + fmtEta(data.duration_s);
        noteEl.textContent = data.fallback
            ? 'Road route unavailable — straight-line distance shown. Use Navigate for turn-by-turn.'
            : 'Road route · updates as you move';
        noteEl.classList.toggle('warn', !!data.fallback);
        if (!fitted) { map.fitBounds(L.latLngBounds(routePoints).pad(0.15)); fitted = true; }
    }
    async function maybeRoute(pos) {
        if (routeInFlight || Date.now() < routeBlockedUntil) return;
        if (routeLine && Date.now() - lastRouteAt < ROUTE_EVERY_MS && distanceToRoute(pos) <= OFF_ROUTE_M) return;
        routeInFlight = true;
        lastRouteAt = Date.now();
        try {
            const resp = await fetch(`${ROUTE_URL}?lat=${pos[0]}&lng=${pos[1]}`, { headers: { 'Accept': 'application/json' } });
            if (resp.status === 429) routeBlockedUntil = Date.now() + ROUTE_EVERY_MS;
            else if (resp.status === 403) { routeBlockedUntil = Infinity; noteEl.textContent = 'This delivery is no longer active — navigation is off.'; }
            else if (resp.ok) drawRoute(await resp.json());
        } catch (e) {
            noteEl.textContent = 'No connection — showing the last route. Navigate still works.';
        } finally {
            routeInFlight = false;
        }
    }

    // ---- live sharing ----------------------------------------------------------
    let watchId = null, stopped = false, lastSent = null, lastSentAt = 0, sending = false;

    function maybeSend(pos, accuracy, speed) {
        if (!SHARING || stopped || sending) return;
        const now = Date.now();
        const due = !lastSent
            || now - lastSentAt >= SEND_EVERY_MS
            || (metres(lastSent, pos) >= SEND_AFTER_M && now - lastSentAt >= MIN_GAP_MS);
        if (!due) return;
        sending = true;
        lastSent = pos;
        lastSentAt = now;
        fetch(LOCATION_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({
                lat: pos[0], lng: pos[1], accuracy: Math.round(accuracy),
                heading: heading === null ? null : Math.round(heading),
                speed: speed === null || isNaN(speed) ? null : Math.round(speed * 10) / 10,
            }),
        }).then(async (resp) => {
            const body = await resp.json().catch(() => ({}));
            if (body.stop) { stopSharing(body.message || 'Location sharing has stopped.'); return; }
            if (resp.ok) setPill('Live location ON', 'on');
        }).catch(() => {
            setPill('Live location ON — reconnecting…', 'warn'); // next fix retries
        }).finally(() => { sending = false; });
    }

    function stopSharing(message) {
        stopped = true;
        if (watchId !== null && navigator.geolocation) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        releaseWakeLock();
        setPill('Sharing stopped', 'off');
        meta.textContent = '';
        setHelp(message + ' You can close this page.');
        routeBlockedUntil = Infinity;
    }

    // "Last shared 4 s ago · ±12 m" — ticks every second.
    let lastAccuracy = null;
    setInterval(() => {
        if (stopped) return;
        const bits = [];
        if (SHARING && lastSentAt) bits.push('Last shared ' + Math.max(0, Math.round((Date.now() - lastSentAt) / 1000)) + ' s ago');
        if (lastAccuracy !== null) bits.push('±' + Math.round(lastAccuracy) + ' m');
        meta.textContent = bits.join(' · ');
    }, 1000);

    // ---- one watcher ------------------------------------------------------------
    let firstFix = false, bestRough = null;

    function onPosition(p) {
        if (stopped) return;
        setHelp('');
        const c = p.coords, acc = c.accuracy, pos = [c.latitude, c.longitude];
        lastAccuracy = acc;
        if (acc > HARD_REJECT_M) return; // an IP/Wi-Fi guess, never GPS

        if (!firstFix) {
            if (acc <= GOOD_ACCURACY_M) {
                firstFix = true;
            } else {
                if (!bestRough || acc < bestRough.acc) bestRough = { pos, acc };
                if (Date.now() - startedAt < GRACE_MS) {
                    setPill('Waiting for a better GPS signal…', 'warn');
                    moveRider(bestRough.pos, bestRough.acc);
                    return;
                }
                firstFix = true; // grace period over: go with the best rough fix
            }
        } else if (acc > GOOD_ACCURACY_M) {
            setPill(SHARING ? 'Live location ON — weak signal, waiting' : 'Weak GPS signal', 'warn');
            return; // after the first fix, inaccurate readings are ignored
        }

        // Heading: the phone's own when moving, else the direction between fixes.
        const speed = c.speed;
        if (c.heading !== null && c.heading !== undefined && !isNaN(c.heading) && speed > 0.5) heading = c.heading;
        else if (lastPos && metres(lastPos, pos) >= 8) heading = bearing(lastPos, pos);
        lastPos = pos;

        if (!SHARING) setPill('Not sharing yet — mark the order Picked Up to start', 'warn');
        else if (!lastSentAt) setPill('Live location ON', 'on');

        moveRider(pos, Math.min(acc, GOOD_ACCURACY_M));
        maybeRoute(pos);
        maybeSend(pos, acc, speed);
    }

    const HOW_TO_ALLOW = '<strong>To turn it on:</strong> Android (Chrome): tap the ⓘ or lock icon left of the address → Permissions → Location → Allow. '
        + 'iPhone (Safari): Settings → Privacy &amp; Security → Location Services → Safari Websites → While Using the App. Then reload this page.';

    function onError(err) {
        if (stopped) return;
        if (err.code === 1) {
            releaseWakeLock(); // nothing to share, so don't hold the screen on
            setPill('Location blocked', 'off');
            setHelp('Location access is blocked, so the customer can\'t see you coming. ' + HOW_TO_ALLOW);
            noteEl.textContent = 'Turn on location to see your route. Navigate still works.';
        } else if (err.code === 2) {
            setPill('GPS is off', 'off');
            setHelp('Your phone can\'t get a location — turn on <strong>Location / GPS</strong> in your quick settings, then wait a few seconds.');
        } else {
            setPill('Still looking for GPS…', 'warn'); // timeout: watchPosition keeps trying
        }
    }

    // ---- screen wake lock (best effort) --------------------------------------------
    let wakeLock = null;
    async function requestWakeLock() {
        if (!SHARING || stopped || !('wakeLock' in navigator)) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
        } catch (e) { /* denied or tab hidden — fine */ }
    }
    function releaseWakeLock() {
        if (wakeLock) wakeLock.release().catch(() => {});
        wakeLock = null;
    }
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') requestWakeLock();
    });

    // ---- start ------------------------------------------------------------------------
    if (!window.isSecureContext) {
        setPill('Location unavailable', 'off');
        setHelp('Location only works on a secure (https) page. The customer\'s pin is shown — use <strong>Navigate</strong> for directions.');
    } else if (!navigator.geolocation) {
        setPill('Location unavailable', 'off');
        setHelp('This browser can\'t share location. Use <strong>Navigate</strong> for directions.');
    } else {
        watchId = navigator.geolocation.watchPosition(onPosition, onError, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
        requestWakeLock();
        if (SHARING && !('wakeLock' in navigator)) {
            setHelp('Keep this screen open while delivering — your phone may stop sharing your location if it locks.');
        }
    }
    window.addEventListener('pagehide', () => {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        releaseWakeLock();
    });
})();
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
