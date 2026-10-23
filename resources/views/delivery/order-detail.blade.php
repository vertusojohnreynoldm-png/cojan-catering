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
            // Matches --status-amber-text in cojan.css (same "pending/warning"
            // status color) — literal here since this is inline JS, not CSS.
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

@if($nav)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
/* Rider navigation map. The customer's pin comes from the server-rendered
   $nav (only present for an active menu delivery); the road route comes
   from GET /delivery/orders/{id}/route, which the server computes from the
   rider's position sent here and the order's pin (never from this page).
   Re-routes only after drifting ~50 m off the drawn line or every 60 s. */
(function () {
    const ROUTE_URL = @json(route('delivery.orders.route', $delivery->id));
    const dest = [{{ $nav['lat'] }}, {{ $nav['lng'] }}];
    const OFF_ROUTE_M = 50;
    const REFRESH_MS = 60000;
    const distEl = document.getElementById('route-distance');
    const etaEl = document.getElementById('route-eta');
    const noteEl = document.getElementById('route-note');
    const mapEl = document.getElementById('rider-map');

    if (!window.L) {
        mapEl.style.display = 'none';
        noteEl.textContent = 'The map could not load — use the Navigate button.';
        return;
    }

    const map = L.map(mapEl, { zoomControl: true, scrollWheelZoom: false }).setView(dest, 16);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);

    L.marker(dest, {
        icon: L.divIcon({ className: 'cj-pin-icon', html: '<span></span>', iconSize: [34, 44], iconAnchor: [17, 42] }),
        title: 'Customer location',
    }).addTo(map);

    let riderMarker = null;
    let accuracyCircle = null;
    let routeLine = null;
    let routePoints = [];
    let lastRouteAt = 0;
    let inFlight = false;
    let fitted = false;
    let blockedUntil = 0;

    const fmtDistance = (m) => m >= 1000 ? (m / 1000).toFixed(m >= 10000 ? 0 : 1) + ' km' : Math.round(m / 10) * 10 + ' m';
    const fmtEta = (s) => s < 60 ? '< 1 min' : (s < 3600 ? Math.round(s / 60) + ' min' : Math.floor(s / 3600) + ' h ' + Math.round((s % 3600) / 60) + ' min');

    // Metres from point p to the drawn route (equirectangular — plenty
    // accurate over a few km).
    function distanceToRoute(p) {
        if (routePoints.length < 2) return Infinity;
        const k = 111320, cos = Math.cos(p[0] * Math.PI / 180);
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
        if (!fitted) {
            map.fitBounds(L.latLngBounds(routePoints).pad(0.15));
            fitted = true;
        }
    }

    async function requestRoute(pos) {
        if (inFlight || Date.now() < blockedUntil) return;
        inFlight = true;
        lastRouteAt = Date.now();
        try {
            const resp = await fetch(`${ROUTE_URL}?lat=${pos[0]}&lng=${pos[1]}`, { headers: { 'Accept': 'application/json' } });
            if (resp.status === 429) {
                blockedUntil = Date.now() + REFRESH_MS; // keep the current route; try again later
            } else if (resp.status === 403) {
                noteEl.textContent = 'This delivery is no longer active — navigation is off.';
                blockedUntil = Infinity;
            } else if (resp.ok) {
                drawRoute(await resp.json());
            }
        } catch (e) {
            noteEl.textContent = 'No connection — showing the last route. Navigate still works.';
        } finally {
            inFlight = false;
        }
    }

    function onPosition(p) {
        const pos = [p.coords.latitude, p.coords.longitude];
        if (!riderMarker) {
            riderMarker = L.marker(pos, {
                icon: L.divIcon({ className: 'cj-rider-dot', html: '<span></span>', iconSize: [22, 22], iconAnchor: [11, 11] }),
                title: 'You', zIndexOffset: 1000,
            }).addTo(map);
            accuracyCircle = L.circle(pos, { radius: p.coords.accuracy || 0, color: '#2563eb', weight: 1, fillOpacity: .08 }).addTo(map);
        } else {
            riderMarker.setLatLng(pos);
            accuracyCircle.setLatLng(pos).setRadius(p.coords.accuracy || 0);
        }

        if (!routeLine || Date.now() - lastRouteAt > REFRESH_MS || distanceToRoute(pos) > OFF_ROUTE_M) {
            requestRoute(pos);
        }
    }

    function onError(err) {
        noteEl.classList.add('warn');
        noteEl.textContent = !window.isSecureContext
            ? 'Location needs a secure (https) page — the customer\'s pin is shown; use Navigate for directions.'
            : (err && err.code === 1
                ? 'Location is blocked — allow it (tap the lock/ⓘ in the address bar → Location → Allow) to see your route. Navigate still works.'
                : 'Can\'t get your location — turn on GPS/Location to see your route. Navigate still works.');
    }

    if (!navigator.geolocation || !window.isSecureContext) {
        onError(null);
    } else {
        navigator.geolocation.watchPosition(onPosition, onError, { enableHighAccuracy: true, maximumAge: 5000, timeout: 20000 });
    }

    setTimeout(() => map.invalidateSize(), 200);
})();
</script>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
@vite(['resources/js/app.js'])
</body>
</html>
