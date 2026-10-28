{{--
    Rider order page: "QR verified at hand-off" (MENU orders only).
      - verified → "QR verified at hand-off at 2:14 PM"
      - picked up / in transit, not verified yet → "Scan customer QR"
        (optional; opens the camera in a modal)
    The scanner uses the browser's built-in BarcodeDetector when it has one,
    otherwise jsQR (loaded only when the scanner opens). Without a camera on
    this page (no https, no permission, old browser) it tells the rider to
    use the phone's Camera app — the QR is a link that verifies the same way.

    Only THIS site's /track/{order number} link for THIS order is accepted;
    the server checks again (Delivery\OrderController::verifyQr).

    Props: delivery (Delivery)
--}}
@props(['delivery'])

@php
    $order = $delivery->order;
    $canScan = !$order->isBooking() && $order->qrStage() === 'active' && !$delivery->qrVerified();
@endphp

@if($order->isBooking())
    {{-- Bookings: no QR hand-off. --}}
@elseif($delivery->qrVerified())
    <p class="cj-qr-verified">
        <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
        QR verified at hand-off at {{ $delivery->qr_scanned_at->format('g:i A') }}
    </p>
@elseif($canScan)
    <div class="cj-qr-scan-box">
        <button type="button" class="btn-cj btn-cj-secondary cj-qr-scan-btn" id="qr-scan-btn">
            <i class="bi bi-qr-code-scan" aria-hidden="true"></i> Scan customer QR
        </button>
        <p class="cj-qr-scan-hint">Optional — confirms you handed the order to the right customer. Ask them to open this order's QR code on their phone.</p>
        <div class="alert-cj alert-warning mb-0" id="qr-scan-fallback" role="alert" hidden></div>
    </div>

    <div class="modal fade" id="qrScanModal" tabindex="-1" aria-labelledby="qrScanTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qrScanTitle">Scan customer QR</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="cj-qr-viewfinder">
                        <video id="qr-video" playsinline muted aria-label="Camera view"></video>
                        <span class="cj-qr-frame" aria-hidden="true"></span>
                    </div>
                    <p class="cj-qr-scan-status" id="qr-scan-status" aria-live="polite">Starting camera…</p>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const VERIFY_URL = {{ \Illuminate\Support\Js::from(route('delivery.orders.verify-qr', $delivery->id)) }};
        const EXPECTED_PATH = {{ \Illuminate\Support\Js::from(parse_url(route('customer.orders.track', $order->order_number), PHP_URL_PATH)) }};
        const HOSTS = {{ \Illuminate\Support\Js::from(\App\Services\QrHandoff::hosts()) }};
        const ORDER_NO = {{ \Illuminate\Support\Js::from($order->order_number) }};
        const JSQR_SRC = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
        const JSQR_SRI = 'sha384-hStSInNIZ8ljtOVrmrgf7zdHMapaLBWoSnPTtF0nzsybp4+LuhDz6sHuEVpWIX8o';
        const CAMERA_APP = 'Open your phone\'s <strong>Camera</strong> app and point it at the customer\'s QR code — it opens this order and verifies it the same way.';

        const btn = document.getElementById('qr-scan-btn');
        const fallback = document.getElementById('qr-scan-fallback');
        const modalEl = document.getElementById('qrScanModal');
        const video = document.getElementById('qr-video');
        const statusEl = document.getElementById('qr-scan-status');

        let stream = null, detector = null, jsQR = null, timer = null, busy = false, lastRejected = '';
        let open = false; // our own flag: Bootstrap adds .show only after its fade

        function showFallback(reason) {
            // One <span>: .alert-cj is a flex row, loose text + <strong> would become columns.
            fallback.innerHTML = '<i class="bi bi-camera" aria-hidden="true"></i><span>' + (reason ? reason + ' ' : '') + CAMERA_APP + '</span>';
            fallback.hidden = false;
        }
        function setStatus(text, tone) {
            statusEl.textContent = text;
            statusEl.className = 'cj-qr-scan-status' + (tone ? ' ' + tone : '');
        }

        // The QR must be this site's /track/{order number} link for THIS order.
        function isThisOrder(text) {
            try {
                const u = new URL(text.trim());
                return /^https?:$/.test(u.protocol) && HOSTS.includes(u.hostname.toLowerCase())
                    && u.pathname.replace(/\/+$/, '') === EXPECTED_PATH;
            } catch (e) { return false; }
        }

        function loadJsQR() {
            if (window.jsQR) return Promise.resolve(window.jsQR);
            return new Promise((resolve, reject) => {
                const s = document.createElement('script');
                s.src = JSQR_SRC; s.integrity = JSQR_SRI; s.crossOrigin = 'anonymous';
                s.onload = () => window.jsQR ? resolve(window.jsQR) : reject(new Error('jsQR missing'));
                s.onerror = () => reject(new Error('jsQR failed to load'));
                document.head.appendChild(s);
            });
        }

        async function pickDecoder() {
            if ('BarcodeDetector' in window) {
                try {
                    const formats = await window.BarcodeDetector.getSupportedFormats();
                    if (formats.includes('qr_code')) { detector = new window.BarcodeDetector({ formats: ['qr_code'] }); return; }
                } catch (e) { /* fall through to jsQR */ }
            }
            jsQR = await loadJsQR();
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        async function decodeFrame() {
            if (video.readyState < 2) return null;
            if (detector) {
                const codes = await detector.detect(video);
                return codes.length ? codes[0].rawValue : null;
            }
            const scale = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
            return code ? code.data : null;
        }

        function getPosition() {
            return new Promise((resolve) => {
                if (!navigator.geolocation) return resolve(null);
                navigator.geolocation.getCurrentPosition(
                    (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy }),
                    () => resolve(null),
                    { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
                );
            });
        }

        async function verify(code) {
            busy = true;
            stopCamera();
            setStatus('QR found — verifying…');
            const pos = await getPosition();
            try {
                const resp = await fetch(VERIFY_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(Object.assign({ code }, pos || {})),
                });
                const data = await resp.json().catch(() => ({}));
                if (resp.ok) { window.location.href = data.redirect || window.location.href; return; }
                setStatus(data.message || 'Could not verify — please try again.', 'error');
                if (data.closed) setTimeout(() => window.location.reload(), 2500);
            } catch (e) {
                setStatus('No connection — check your signal and tap Scan customer QR again.', 'error');
            }
            busy = false;
        }

        async function scanLoop() {
            if (busy || !stream) return;
            try {
                const text = await decodeFrame();
                if (text && isThisOrder(text)) { verify(text); return; }
                if (text && text !== lastRejected) {
                    lastRejected = text;
                    setStatus('That QR code isn\'t for Order #' + ORDER_NO + '. Ask the customer to open this order\'s QR.', 'error');
                }
            } catch (e) { /* a bad frame — try the next one */ }
            timer = setTimeout(scanLoop, 200);
        }

        function stopCamera() {
            clearTimeout(timer);
            if (stream) stream.getTracks().forEach((t) => t.stop());
            stream = null;
            video.srcObject = null;
        }

        btn.addEventListener('click', async () => {
            fallback.hidden = true;
            if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showFallback('This browser can\'t open the camera here.');
                return;
            }
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            busy = false; lastRejected = ''; open = true;
            setStatus('Starting camera…');
            modal.show();
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                await pickDecoder();
                if (!open) { stopCamera(); return; } // closed while the camera was starting
                video.srcObject = stream;
                await video.play();
                setStatus('Point the camera at the customer\'s QR code.');
                scanLoop();
            } catch (e) {
                stopCamera();
                modal.hide();
                showFallback(e && e.name === 'NotAllowedError'
                    ? 'Camera permission was blocked — allow it in your browser settings, or:'
                    : 'The camera scanner couldn\'t start.');
            }
        });

        modalEl.addEventListener('hide.bs.modal', () => { open = false; });
        modalEl.addEventListener('hidden.bs.modal', stopCamera);
        window.addEventListener('pagehide', stopCamera);
    })();
    </script>
@endif
