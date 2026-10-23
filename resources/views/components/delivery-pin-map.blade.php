{{--
    Delivery-location pin for MENU-ORDER checkout (San Jose only). Draggable
    Leaflet pin + "Use my current location", centred on the chosen barangay
    (coordinates from the committed database/data/barangay_coords.php — no
    runtime geocoding). Writes latitude/longitude hidden inputs; the server
    re-validates the pin against the San Jose box (DeliveryLocation).

    Props: config — DeliveryLocation::checkoutMapConfig() + ['saved' => [lat,lng]|null]
           barangaySelect — id of the barangay <select> to follow
--}}
@props(['config', 'barangaySelect' => 'checkout-barangay'])

@php
    $oldLat = old('latitude');
    $oldLng = old('longitude');
@endphp

<div class="mb-3 cj-pin-field">
    <label class="cj-label" id="pin-label">Delivery Location on Map <span style="color:var(--required);">*</span></label>
    <p class="cj-pin-help">
        Drag the pin (or tap the map) to your exact gate or door — this is where the rider will navigate to.
    </p>
    <div id="pin-map" class="cj-pin-map" role="application" aria-labelledby="pin-label"></div>
    <div class="cj-pin-actions">
        <button type="button" class="btn-cj btn-cj-sm" id="pin-locate">
            <i class="bi bi-crosshair"></i> Use my current location
        </button>
        <span id="pin-status" class="cj-pin-status" aria-live="polite"></span>
    </div>
    <input type="hidden" name="latitude" id="pin-lat" value="{{ $oldLat }}">
    <input type="hidden" name="longitude" id="pin-lng" value="{{ $oldLng }}">
    <label class="cj-pin-save">
        <input type="checkbox" name="save_location" value="1" {{ old('_token') ? (old('save_location') ? 'checked' : '') : 'checked' }}>
        <span>Save as my default location</span>
    </label>
    @error('latitude')
        <small class="cj-field-error" role="alert">{{ $message }}</small>
    @enderror
    <small class="cj-field-error" id="pin-error" style="display:none;" role="alert"></small>
</div>

@once
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endonce
<script>
(function () {
    const cfg = @json($config);
    const latIn = document.getElementById('pin-lat');
    const lngIn = document.getElementById('pin-lng');
    const statusEl = document.getElementById('pin-status');
    const errorEl = document.getElementById('pin-error');
    const locateBtn = document.getElementById('pin-locate');
    const barangaySel = document.getElementById(@json($barangaySelect));
    const mapEl = document.getElementById('pin-map');

    const inside = (lat, lng) => lat >= cfg.bounds.south && lat <= cfg.bounds.north && lng >= cfg.bounds.west && lng <= cfg.bounds.east;
    const centerFor = (barangay) => cfg.barangays[barangay] || cfg.townCenter;
    const showError = (msg) => { errorEl.textContent = msg; errorEl.style.display = msg ? 'block' : 'none'; };

    if (!window.L) {
        // CDN blocked/offline: no map, but say so instead of silently failing.
        mapEl.style.display = 'none';
        statusEl.textContent = 'The map could not load — please refresh the page to set your location.';
        return;
    }

    const hasOld = latIn.value !== '' && lngIn.value !== '';
    let pinSet = hasOld || !!cfg.saved; // a real pin vs. just the barangay-centre suggestion
    const start = hasOld ? [parseFloat(latIn.value), parseFloat(lngIn.value)]
        : (cfg.saved || centerFor(barangaySel && barangaySel.value));

    const isTouch = window.matchMedia('(pointer: coarse)').matches;
    const map = L.map(mapEl, {
        center: start,
        zoom: pinSet ? 17 : 15,
        scrollWheelZoom: false,
        // One finger scrolls the PAGE on phones (no map trap); two fingers
        // pan/zoom the map. The pin itself is still dragged with one finger.
        dragging: !isTouch,
        tap: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);

    const icon = L.divIcon({ className: 'cj-pin-icon', html: '<span></span>', iconSize: [34, 44], iconAnchor: [17, 42] });
    const marker = L.marker(start, { draggable: true, icon, keyboard: true, title: 'Delivery location — drag to adjust' }).addTo(map);

    function render() {
        marker.getElement()?.classList.toggle('cj-pin-unset', !pinSet);
        if (pinSet) {
            const ll = marker.getLatLng();
            statusEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> Pin set (' + ll.lat.toFixed(5) + ', ' + ll.lng.toFixed(5) + ')';
            statusEl.className = 'cj-pin-status set';
        } else {
            statusEl.textContent = 'Not set yet — drag the pin or tap the map.';
            statusEl.className = 'cj-pin-status';
        }
    }

    function setPin(lat, lng, zoom) {
        if (!inside(lat, lng)) {
            showError('That spot is outside San Jose, Occidental Mindoro — menu orders deliver only within San Jose.');
            return false;
        }
        showError('');
        marker.setLatLng([lat, lng]);
        latIn.value = lat.toFixed(7);
        lngIn.value = lng.toFixed(7);
        pinSet = true;
        if (zoom) map.setView([lat, lng], zoom);
        render();
        return true;
    }

    if (pinSet && !hasOld) setPin(start[0], start[1]); // saved default → fill the inputs

    marker.on('dragend', () => {
        const ll = marker.getLatLng();
        if (!setPin(ll.lat, ll.lng) && latIn.value) {
            marker.setLatLng([parseFloat(latIn.value), parseFloat(lngIn.value)]); // snap back to the last valid spot
        }
    });
    map.on('click', (e) => setPin(e.latlng.lat, e.latlng.lng));

    // Follow the barangay dropdown until the customer places the pin themselves.
    barangaySel && barangaySel.addEventListener('change', () => {
        if (pinSet) return;
        const c = centerFor(barangaySel.value);
        marker.setLatLng(c);
        map.setView(c, 16);
    });

    locateBtn.addEventListener('click', () => {
        if (!window.isSecureContext) {
            // Browsers only share location over https (the live site is) —
            // this mostly happens on a local http:// dev copy.
            showError('Your browser only shares location on a secure (https) page — please drag the pin to your address instead.');
            return;
        }
        if (!navigator.geolocation) {
            showError('Your browser can\'t share your location — drag the pin to your address instead.');
            return;
        }
        locateBtn.disabled = true;
        statusEl.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                locateBtn.disabled = false;
                if (!setPin(pos.coords.latitude, pos.coords.longitude, 17)) render();
            },
            (err) => {
                locateBtn.disabled = false;
                render();
                showError(err.code === err.PERMISSION_DENIED
                    ? 'Location access is blocked. Allow it for this site (tap the lock/ⓘ icon in the address bar → Location → Allow), or just drag the pin.'
                    : 'Couldn\'t get your location right now — make sure Location/GPS is turned on, or drag the pin instead.');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
        );
    });

    // Never let the order go through without a real pin (the server checks too).
    const form = mapEl.closest('form');
    form && form.addEventListener('submit', (e) => {
        if (!latIn.value || !lngIn.value) {
            e.preventDefault();
            showError('Please set your delivery location on the map — drag the pin or tap "Use my current location".');
            mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    render();
    // Leaflet measures its container on init; re-measure once layout settles.
    setTimeout(() => map.invalidateSize(), 200);
})();
</script>
